<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegenerateProductSkusRequest;
use App\Http\Requests\Admin\SyncProductVariantsRequest;
use App\Models\Product;
use App\Services\VariantService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class ProductVariantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private VariantService $variantService) {}

    public function syncAxes(SyncProductVariantsRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->variantService->syncAxes($product, $request->input('axes', []));

        return back()->with('status', 'Variant axes saved.');
    }

    public function regenerateSkus(RegenerateProductSkusRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $created = $this->variantService->regenerateSkus(
            $product,
            (int) $request->input('default_price_amount'),
            (string) ($request->input('currency') ?: 'gbp'),
            (int) ($request->input('default_stock_on_hand') ?? 0),
        );

        return back()->with('status', $created > 0
            ? "Generated {$created} new SKU(s)."
            : 'No new SKUs needed — all combinations already exist.');
    }
}
