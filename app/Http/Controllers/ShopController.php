<?php

namespace App\Http\Controllers;

use App\Enums\ProductSaleStatus;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function __invoke(): Response
    {
        $products = Product::query()
            ->where('is_public', true)
            ->where('sell_through_ltdedn', true)
            ->where('is_sellable', true)
            ->where('sale_status', ProductSaleStatus::Active)
            ->where(function ($query) {
                $query->whereHas('editions', function ($editionQuery) {
                    $editionQuery->whereNull('product_sku_id')
                        ->where('status', 'available');
                })->orWhereHas('skus', function ($skuQuery) {
                    $skuQuery->where('is_active', true)
                        ->whereHas('editions', function ($editionQuery) {
                            $editionQuery->where('status', 'available');
                        });
                });
            })
            ->with(['artist:id,slug', 'skus' => function ($query) {
                $query->where('is_active', true)
                    ->whereHas('editions', function ($editionQuery) {
                        $editionQuery->where('status', 'available');
                    })
                    ->orderBy('id');
            }])
            ->orderBy('id')
            ->limit(24)
            ->get(['id', 'artist_id', 'name', 'slug', 'cover_image'])
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'artist_id' => $product->artist_id,
                    'image' => $product->cover_image ? '/storage/'.$product->cover_image : null,
                    'shop_url' => route('shop.product.slug', ['artistSlug' => $product->artist->slug, 'productSlug' => $product->slug]),
                    'skus' => $product->skus->map(function ($sku) {
                        return [
                            'id' => $sku->id,
                            'sku_code' => $sku->sku_code,
                            'price_amount' => (int) $sku->price_amount,
                            'price' => number_format(((int) $sku->price_amount) / 100, 2, '.', ''),
                            'currency' => $sku->currency,
                            'stock_available' => $sku->stock_available,
                            'attributes' => $sku->attributes ?: [],
                        ];
                    })->values(),
                ];
            })
            ->values();

        return Inertia::render('Shop', [
            'products' => $products,
        ]);
    }
}
