<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
use App\Enums\ProductSaleStatus;
use App\Models\Artist;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShopArtistController extends Controller
{
    public function show(string $artistSlug): Response
    {
        $artist = Artist::query()->where('slug', $artistSlug)->first();
        if (! $artist) {
            abort(404);
        }

        $products = $this->productsQuery($artist)
            ->orderBy('id')
            ->limit(48)
            ->get(['id', 'artist_id', 'name', 'slug', 'cover_image'])
            ->map(function (Product $product) use ($artist) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'image' => $product->cover_image ? '/storage/'.$product->cover_image : null,
                    'shop_url' => route('shop.product.slug', [
                        'artistSlug' => $artist->slug,
                        'productSlug' => $product->slug,
                    ]),
                ];
            })
            ->values();

        return Inertia::render('Shop/Artist', [
            'artist' => [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'bio' => $artist->bio,
                'hero_image' => $artist->hero_image ? '/storage/'.$artist->hero_image : null,
            ],
            'products' => $products,
        ]);
    }

    private function productsQuery(Artist $artist): Builder
    {
        return Product::query()
            ->where('artist_id', $artist->id)
            ->when(! Auth::check(), function (Builder $query) {
                $query->where('is_public', true);
            })
            ->where('sell_through_ltdedn', true)
            ->where('is_sellable', true)
            ->where('sale_status', ProductSaleStatus::Active)
            ->where(function ($query) {
                $query->whereHas('editions', function ($editionQuery) {
                    $editionQuery->whereNull('product_sku_id')
                        ->where('status', ProductEditionStatus::Available);
                })->orWhereHas('skus', function ($skuQuery) {
                    $skuQuery->where('is_active', true)
                        ->whereHas('editions', function ($editionQuery) {
                            $editionQuery->where('status', ProductEditionStatus::Available);
                        });
                });
            });
    }
}
