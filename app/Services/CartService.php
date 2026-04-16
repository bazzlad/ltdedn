<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartService
{
    public const COOKIE_NAME = 'ltdedn_cart';

    public const COOKIE_TTL_MINUTES = 60 * 24 * 30;

    public function resolveForRequest(Request $request): Cart
    {
        $user = $request->user();

        if ($user) {
            $cart = Cart::query()->where('user_id', $user->id)->first();
            if ($cart) {
                return $cart;
            }

            return Cart::query()->create([
                'user_id' => $user->id,
                'session_token' => null,
                'currency' => 'gbp',
                'expires_at' => null,
            ]);
        }

        $token = (string) $request->cookie(self::COOKIE_NAME, '');
        if ($token !== '') {
            $cart = Cart::query()->where('session_token', $token)->first();
            if ($cart) {
                return $cart;
            }
        }

        $newToken = Str::random(48);
        Cookie::queue(
            self::COOKIE_NAME,
            $newToken,
            self::COOKIE_TTL_MINUTES,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );

        return Cart::query()->create([
            'user_id' => null,
            'session_token' => $newToken,
            'currency' => 'gbp',
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * @return array{ok: bool, item?: CartItem, error?: string, clamped?: bool}
     */
    public function addItem(Cart $cart, Product $product, ?ProductSku $sku, int $quantity): array
    {
        if ($quantity < 1) {
            return ['ok' => false, 'error' => 'Quantity must be at least 1.'];
        }

        $userId = $cart->user_id;
        $check = ProductAvailability::assertPurchasable($product, $sku, $userId);
        if (! $check['ok']) {
            return ['ok' => false, 'error' => (string) $check['error']];
        }

        return DB::transaction(function () use ($cart, $product, $sku, $quantity) {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $query = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id);

            if ($sku) {
                $query->where('product_sku_id', $sku->id);
            } else {
                $query->whereNull('product_sku_id');
            }

            $item = $query->lockForUpdate()->first();
            $desiredQty = ($item ? (int) $item->quantity : 0) + $quantity;
            $clamped = false;

            $available = $this->availableForLine($product, $sku);
            if ($available <= 0) {
                return ['ok' => false, 'error' => 'This item is sold out.'];
            }

            if ($desiredQty > $available) {
                $desiredQty = $available;
                $clamped = true;
            }

            $unitAmount = ProductAvailability::resolvePrice($product, $sku);

            if ($item) {
                $item->update([
                    'quantity' => $desiredQty,
                    'unit_amount_snapshot' => $unitAmount,
                ]);
            } else {
                $item = CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_sku_id' => $sku?->id,
                    'quantity' => $desiredQty,
                    'unit_amount_snapshot' => $unitAmount,
                ]);
            }

            return ['ok' => true, 'item' => $item, 'clamped' => $clamped];
        });
    }

    /**
     * @return array{ok: bool, item?: CartItem, error?: string, clamped?: bool}
     */
    public function updateQuantity(CartItem $item, int $quantity): array
    {
        if ($quantity < 1) {
            return $this->removeItem($item);
        }

        return DB::transaction(function () use ($item, $quantity) {
            $locked = CartItem::query()->whereKey($item->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['ok' => false, 'error' => 'Item not found.'];
            }

            $product = $locked->product()->first();
            $sku = $locked->product_sku_id ? ProductSku::query()->find($locked->product_sku_id) : null;
            $clamped = false;

            if ($product) {
                $available = $this->availableForLine($product, $sku);
                if ($quantity > $available) {
                    $quantity = max($available, 0);
                    $clamped = true;
                }
                if ($quantity < 1) {
                    $locked->delete();

                    return ['ok' => true, 'clamped' => true];
                }
            }

            $locked->update(['quantity' => $quantity]);

            return ['ok' => true, 'item' => $locked->fresh(), 'clamped' => $clamped];
        });
    }

    /**
     * @return array{ok: bool}
     */
    public function removeItem(CartItem $item): array
    {
        $item->delete();

        return ['ok' => true];
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    /**
     * Upper bound for a cart line's quantity.
     *
     * SKU lines are gated by the SKU's stock_available (already subtracts
     * live reservations via stock_reserved). Standard lines (no SKU) have
     * no SKU-level stock counter, so they're gated by the count of
     * available edition rows not already held by a live reservation —
     * which is exactly what checkout enforces and what the product page
     * displays.
     */
    private function availableForLine(Product $product, ?ProductSku $sku): int
    {
        if ($sku) {
            return (int) ($sku->fresh()?->stock_available ?? 0);
        }

        return ProductAvailability::availableForLine($product, null);
    }

    /**
     * Merge a guest cart's items into the user's cart. Guest cart is deleted after.
     * Clamps combined quantities to each SKU's available stock.
     */
    public function mergeGuestIntoUser(Cart $guest, Cart $user): Cart
    {
        if ($guest->id === $user->id) {
            return $user;
        }

        return DB::transaction(function () use ($guest, $user) {
            $lockedGuest = Cart::query()->whereKey($guest->id)->lockForUpdate()->first();
            $lockedUser = Cart::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $lockedGuest || ! $lockedUser) {
                return $user;
            }

            $guestItems = $lockedGuest->items()->lockForUpdate()->get();

            foreach ($guestItems as $gi) {
                $existing = CartItem::query()
                    ->where('cart_id', $lockedUser->id)
                    ->where('product_id', $gi->product_id)
                    ->where(function ($q) use ($gi) {
                        $gi->product_sku_id
                            ? $q->where('product_sku_id', $gi->product_sku_id)
                            : $q->whereNull('product_sku_id');
                    })
                    ->lockForUpdate()
                    ->first();

                $combined = (int) $gi->quantity + (int) ($existing->quantity ?? 0);

                $product = $gi->product()->first();
                $sku = $gi->product_sku_id ? ProductSku::query()->find($gi->product_sku_id) : null;
                if ($product) {
                    $available = $this->availableForLine($product, $sku);
                    if ($combined > $available) {
                        $combined = $available;
                    }
                }

                if ($combined < 1) {
                    if ($existing) {
                        $existing->delete();
                    }

                    continue;
                }

                if ($existing) {
                    $existing->update([
                        'quantity' => $combined,
                        'unit_amount_snapshot' => $gi->unit_amount_snapshot,
                    ]);
                } else {
                    CartItem::query()->create([
                        'cart_id' => $lockedUser->id,
                        'product_id' => $gi->product_id,
                        'product_sku_id' => $gi->product_sku_id,
                        'quantity' => $combined,
                        'unit_amount_snapshot' => $gi->unit_amount_snapshot,
                    ]);
                }
            }

            $lockedGuest->items()->delete();
            $lockedGuest->delete();

            Cookie::queue(Cookie::forget(self::COOKIE_NAME));

            return $lockedUser->fresh();
        });
    }

    /**
     * Recompute prices from live Product/ProductSku, return a snapshot the UI can render.
     *
     * @return array{
     *     currency: string,
     *     subtotal: int,
     *     item_count: int,
     *     lines: list<array{
     *         id: int,
     *         product_id: int,
     *         product_sku_id: ?int,
     *         product_name: string,
     *         sku_code: ?string,
     *         quantity: int,
     *         unit_amount: int,
     *         line_total: int,
     *         available: ?int,
     *         ok: bool,
     *         error: ?string
     *     }>
     * }
     */
    public function pricingSnapshot(Cart $cart): array
    {
        $cart->loadMissing(['items.product', 'items.sku']);

        $lines = [];
        $subtotal = 0;
        $count = 0;
        $currency = (string) ($cart->currency ?: 'gbp');

        foreach ($cart->items as $item) {
            /** @var Product|null $product */
            $product = $item->product;
            /** @var ProductSku|null $sku */
            $sku = $item->sku;

            $ok = true;
            $error = null;

            if (! $product) {
                $ok = false;
                $error = 'Product no longer exists.';
                $unit = (int) $item->unit_amount_snapshot;
            } else {
                $check = ProductAvailability::assertPurchasable($product, $sku, $cart->user_id);
                if (! $check['ok']) {
                    $ok = false;
                    $error = (string) $check['error'];
                }
                $unit = ProductAvailability::resolvePrice($product, $sku);
                $currency = ProductAvailability::resolveCurrency($product, $sku);
            }

            $available = $product ? $this->availableForLine($product, $sku) : null;
            $quantity = (int) $item->quantity;
            if ($available !== null && $quantity > $available) {
                $ok = false;
                $error = 'Only '.$available.' in stock.';
            }

            $lineTotal = $unit * $quantity;
            if ($ok) {
                $subtotal += $lineTotal;
                $count += $quantity;
            }

            $lines[] = [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'product_sku_id' => $item->product_sku_id ? (int) $item->product_sku_id : null,
                'product_name' => $product ? (string) $product->name : '(removed)',
                'sku_code' => $sku ? (string) $sku->sku_code : null,
                'quantity' => $quantity,
                'unit_amount' => $unit,
                'line_total' => $lineTotal,
                'available' => $available,
                'ok' => $ok,
                'error' => $error,
            ];
        }

        return [
            'currency' => $currency,
            'subtotal' => $subtotal,
            'item_count' => $count,
            'lines' => $lines,
        ];
    }
}
