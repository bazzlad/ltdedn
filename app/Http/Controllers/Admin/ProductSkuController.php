<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductSkuRequest;
use App\Http\Requests\Admin\UpdateProductSkuRequest;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\SkuStockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductSkuController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private StockAdjustmentService $stockAdjustmentService) {}

    public function index(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        return Inertia::render('Admin/Products/Skus/Index', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'skus' => $product->skus()->orderBy('id')->get()->map(function (ProductSku $sku) {
                return [
                    'id' => $sku->id,
                    'sku_code' => $sku->sku_code,
                    'price_amount' => (int) $sku->price_amount,
                    'currency' => $sku->currency,
                    'stock_on_hand' => (int) $sku->stock_on_hand,
                    'stock_reserved' => (int) $sku->stock_reserved,
                    'stock_available' => (int) $sku->stock_available,
                    'is_active' => (bool) $sku->is_active,
                    'attributes' => $sku->attributes ?: [],
                ];
            })->values(),
            'adjustments' => $product->skus()
                ->get()
                ->pluck('id')
                ->pipe(function ($ids) use ($validated) {
                    $query = SkuStockAdjustment::query()
                        ->whereIn('product_sku_id', $ids)
                        ->with('actor:id,name');

                    if (! empty($validated['q'])) {
                        $q = (string) $validated['q'];
                        $query->where(function ($builder) use ($q) {
                            $builder->where('reason', 'like', '%'.$q.'%')
                                ->orWhere('source', 'like', '%'.$q.'%')
                                ->orWhereHas('actor', function ($actorQ) use ($q) {
                                    $actorQ->where('name', 'like', '%'.$q.'%');
                                });
                        });
                    }

                    if (! empty($validated['reason'])) {
                        $query->where('reason', $validated['reason']);
                    }

                    if (! empty($validated['source'])) {
                        $query->where('source', $validated['source']);
                    }

                    return $query->latest('id')
                        ->limit(100)
                        ->get()
                        ->map(function ($adj) {
                            return [
                                'id' => $adj->id,
                                'sku_id' => $adj->product_sku_id,
                                'delta_on_hand' => (int) $adj->delta_on_hand,
                                'before_on_hand' => (int) $adj->before_on_hand,
                                'after_on_hand' => (int) $adj->after_on_hand,
                                'reason' => $adj->reason,
                                'source' => $adj->source,
                                'actor_name' => $adj->actor?->name,
                                'created_at' => (string) $adj->created_at,
                            ];
                        })
                        ->values();
                }),
            'adjustmentFilters' => [
                'q' => $validated['q'] ?? '',
                'reason' => $validated['reason'] ?? '',
                'source' => $validated['source'] ?? '',
            ],
        ]);
    }

    public function store(StoreProductSkuRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['currency'] = strtolower((string) $validated['currency']);
        $validated['product_id'] = $product->id;
        $validated['stock_reserved'] = 0;

        $sku = ProductSku::create($validated);

        $this->stockAdjustmentService->record(
            sku: $sku,
            deltaOnHand: (int) $sku->stock_on_hand,
            beforeOnHand: 0,
            afterOnHand: (int) $sku->stock_on_hand,
            reason: 'initial_stock',
            source: 'admin',
            actorUserId: $request->user() ? $request->user()->id : null,
            meta: ['action' => 'sku_create']
        );

        return redirect()->route('admin.products.skus.index', $product)
            ->with('success', 'SKU created.');
    }

    public function update(UpdateProductSkuRequest $request, Product $product, ProductSku $sku): RedirectResponse
    {
        $this->authorize('update', $product);

        if ($sku->product_id !== $product->id) {
            abort(404);
        }

        $validated = $request->validated();

        if ((int) $validated['stock_on_hand'] < (int) $sku->stock_reserved) {
            return back()->withErrors([
                'stock_on_hand' => 'Stock on hand cannot be lower than currently reserved stock ('.$sku->stock_reserved.').',
            ]);
        }

        $beforeOnHand = (int) $sku->stock_on_hand;

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['currency'] = strtolower((string) $validated['currency']);

        $sku->update($validated);

        $afterOnHand = (int) $sku->fresh()->stock_on_hand;
        $delta = $afterOnHand - $beforeOnHand;

        if ($delta !== 0) {
            $this->stockAdjustmentService->record(
                sku: $sku,
                deltaOnHand: $delta,
                beforeOnHand: $beforeOnHand,
                afterOnHand: $afterOnHand,
                reason: 'manual_stock_update',
                source: 'admin',
                actorUserId: $request->user() ? $request->user()->id : null,
                meta: ['action' => 'sku_update']
            );
        }

        return redirect()->route('admin.products.skus.index', $product)
            ->with('success', 'SKU updated.');
    }

    public function destroy(Product $product, ProductSku $sku): RedirectResponse
    {
        $this->authorize('update', $product);

        if ($sku->product_id !== $product->id) {
            abort(404);
        }

        if ((int) $sku->stock_reserved > 0) {
            return back()->withErrors([
                'sku' => 'Cannot delete SKU with reserved stock.',
            ]);
        }

        if ($sku->orderItems()->exists() || SkuStockAdjustment::where('product_sku_id', $sku->id)->exists()) {
            return back()->withErrors([
                'sku' => 'Cannot delete SKU with order or stock adjustment history. Deactivate it instead.',
            ]);
        }

        $sku->delete();

        return redirect()->route('admin.products.skus.index', $product)
            ->with('success', 'SKU deleted.');
    }
}
