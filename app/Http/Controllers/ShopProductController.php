<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
use App\Enums\ProductSaleStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShopProductController extends Controller
{
    public function byId(int $artistId, int $productId): Response|RedirectResponse
    {
        $product = $this->baseQuery()
            ->where('id', $productId)
            ->where('artist_id', $artistId)
            ->first(['id', 'artist_id', 'name', 'slug', 'cover_image', 'base_price', 'currency', 'is_public']);

        if (! $product) {
            return $this->loginOrFail($artistId, $productId);
        }

        return $this->renderProduct($product);
    }

    public function bySlug(string $artistSlug, string $productSlug): Response|RedirectResponse
    {
        $product = $this->baseQuery()
            ->where('slug', $productSlug)
            ->whereHas('artist', function (Builder $query) use ($artistSlug) {
                $query->where('slug', $artistSlug);
            })
            ->first(['id', 'artist_id', 'name', 'slug', 'cover_image', 'base_price', 'currency', 'is_public']);

        if (! $product) {
            return $this->loginOrFailBySlug($artistSlug, $productSlug);
        }

        return $this->renderProduct($product);
    }

    private function baseQuery(): Builder
    {
        return Product::query()
            ->where('sell_through_ltdedn', true)
            ->where('is_sellable', true)
            ->where('sale_status', ProductSaleStatus::Active)
            ->whereHas('editions')
            ->with(['artist:id,name,slug', 'skus' => function ($query) {
                $query->where('is_active', true)
                    ->whereHas('editions', function ($editionQuery) {
                        $editionQuery->where('status', ProductEditionStatus::Available);
                    })
                    ->orderBy('id');
            }]);
    }

    private function loginOrFail(int $artistId, int $productId): Response|RedirectResponse
    {
        if (Auth::check()) {
            abort(404);
        }

        $exists = Product::query()
            ->where('id', $productId)
            ->where('artist_id', $artistId)
            ->where('is_public', false)
            ->where('sell_through_ltdedn', true)
            ->where('is_sellable', true)
            ->where('sale_status', ProductSaleStatus::Active)
            ->whereHas('editions')
            ->exists();

        if (! $exists) {
            abort(404);
        }

        return redirect()->guest(route('login'))
            ->with('status', 'You must be logged in to view this product.');
    }

    private function loginOrFailBySlug(string $artistSlug, string $productSlug): Response|RedirectResponse
    {
        if (Auth::check()) {
            abort(404);
        }

        $exists = Product::query()
            ->where('slug', $productSlug)
            ->where('is_public', false)
            ->where('sell_through_ltdedn', true)
            ->where('is_sellable', true)
            ->where('sale_status', ProductSaleStatus::Active)
            ->whereHas('editions')
            ->whereHas('artist', function (Builder $query) use ($artistSlug) {
                $query->where('slug', $artistSlug);
            })
            ->exists();

        if (! $exists) {
            abort(404);
        }

        return redirect()->guest(route('login'))
            ->with('status', 'You must be logged in to view this product.');
    }

    private function renderProduct(Product $product): Response
    {
        $reservedEditionIdsQuery = function ($query) {
            $query->select('product_edition_id')
                ->from('inventory_reservations')
                ->where('status', 'active')
                ->whereNotNull('product_edition_id')
                ->where('expires_at', '>', now());
        };

        $standardAvailable = $product->editions()
            ->whereNull('product_sku_id')
            ->where('status', ProductEditionStatus::Available)
            ->whereNotIn('id', $reservedEditionIdsQuery)
            ->count();

        return Inertia::render('ShopProduct', [
            'product' => [
                'id' => $product->id,
                'artist_id' => $product->artist_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'artist_name' => $product->artist ? $product->artist->name : null,
                'artist_slug' => $product->artist ? $product->artist->slug : null,
                'image' => $product->cover_image ? '/storage/'.$product->cover_image : null,
                'base_price' => $product->base_price,
                'standard_available' => $standardAvailable,
                'skus' => $product->skus->map(function ($sku) use ($reservedEditionIdsQuery, $product) {
                    $skuEditionAvailable = $product->editions()
                        ->where('product_sku_id', $sku->id)
                        ->where('status', ProductEditionStatus::Available)
                        ->whereNotIn('id', $reservedEditionIdsQuery)
                        ->count();

                    return [
                        'id' => $sku->id,
                        'sku_code' => $sku->sku_code,
                        'price_amount' => (int) $sku->price_amount,
                        'price' => number_format(((int) $sku->price_amount) / 100, 2, '.', ''),
                        'currency' => $sku->currency,
                        'stock_available' => $skuEditionAvailable,
                        'attributes' => $sku->attributes ?: [],
                    ];
                })->values(),
            ],
        ]);
    }
}
