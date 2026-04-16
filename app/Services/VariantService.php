<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSku;
use App\Models\ProductVariantAxis;
use App\Models\ProductVariantValue;
use Illuminate\Support\Facades\DB;

class VariantService
{
    /**
     * Replace the product's axes + values with the supplied list.
     *
     * $axes shape:
     * [
     *   ['id' => ?int, 'name' => 'Size', 'sort_order' => 0, 'values' => [
     *       ['id' => ?int, 'value' => 'S', 'sort_order' => 0],
     *       ...
     *   ]],
     *   ...
     * ]
     *
     * Existing SKU→value pivot rows survive axis/value renames because we
     * key on ID when provided. Deleting an axis cascades value + pivot
     * cleanup at the DB layer (cascadeOnDelete). Deleting a value is
     * blocked if any SKU still references it (pivot FK is restrictOnDelete).
     *
     * @param  array<int, array{id?: ?int, name: string, sort_order?: int, values?: array<int, array{id?: ?int, value: string, sort_order?: int}>}>  $axes
     */
    public function syncAxes(Product $product, array $axes): void
    {
        DB::transaction(function () use ($product, $axes) {
            $existingAxes = $product->variantAxes()->with('values')->get()->keyBy('id');
            $keptAxisIds = [];

            foreach ($axes as $axisIdx => $axisInput) {
                $axisId = isset($axisInput['id']) ? (int) $axisInput['id'] : null;
                $axis = $axisId && $existingAxes->has($axisId)
                    ? $existingAxes->get($axisId)
                    : new ProductVariantAxis(['product_id' => $product->id]);

                $axis->product_id = $product->id;
                $axis->name = (string) $axisInput['name'];
                $axis->sort_order = (int) ($axisInput['sort_order'] ?? $axisIdx);
                $axis->save();

                $keptAxisIds[] = $axis->id;
                $existingValues = $axis->values()->get()->keyBy('id');
                $keptValueIds = [];

                foreach ((array) ($axisInput['values'] ?? []) as $valueIdx => $valueInput) {
                    $valueId = isset($valueInput['id']) ? (int) $valueInput['id'] : null;
                    $value = $valueId && $existingValues->has($valueId)
                        ? $existingValues->get($valueId)
                        : new ProductVariantValue(['product_variant_axis_id' => $axis->id]);

                    $value->product_variant_axis_id = $axis->id;
                    $value->value = (string) $valueInput['value'];
                    $value->sort_order = (int) ($valueInput['sort_order'] ?? $valueIdx);
                    $value->save();

                    $keptValueIds[] = $value->id;
                }

                // Values no longer in the incoming list: delete if unused,
                // otherwise mark inactive so regeneration ignores them but
                // historical SKU snapshots still resolve.
                $axis->values()->whereNotIn('id', $keptValueIds)->each(function (ProductVariantValue $v) {
                    if (DB::table('product_sku_variant_values')->where('product_variant_value_id', $v->id)->exists()) {
                        if ($v->is_active) {
                            $v->update(['is_active' => false]);
                        }

                        return;
                    }

                    $v->delete();
                });
            }

            $product->variantAxes()->whereNotIn('id', $keptAxisIds)->each(function (ProductVariantAxis $axis) {
                $axis->loadMissing('values');
                $blocked = $axis->values->contains(function (ProductVariantValue $v) {
                    return DB::table('product_sku_variant_values')->where('product_variant_value_id', $v->id)->exists();
                });

                if ($blocked) {
                    return;
                }

                $axis->delete();
            });
        });
    }

    /**
     * Idempotent Cartesian SKU regeneration.
     *
     * - For every combination of one value per axis, ensures an active SKU
     *   exists with the matching pivot rows.
     * - Legacy SKUs (with attributes JSON but no pivot rows) are left alone
     *   and keep working through the existing attributes path.
     * - A SKU that is no longer reachable by any axis combination is
     *   deactivated (never deleted) if it has orders or editions; otherwise
     *   deleted to keep the catalogue tidy.
     *
     * Returns the number of SKUs created this call.
     */
    public function regenerateSkus(Product $product, int $defaultPriceAmount, string $currency = 'gbp', int $defaultStockOnHand = 0): int
    {
        $axes = $product->variantAxes()->with(['values' => fn ($q) => $q->where('is_active', true)])->get();

        if ($axes->isEmpty() || $axes->contains(fn ($a) => $a->values->isEmpty())) {
            return 0;
        }

        $combinations = $this->cartesian($axes->map(fn ($a) => $a->values->all())->all());

        $created = 0;

        DB::transaction(function () use ($product, $combinations, $defaultPriceAmount, $currency, $defaultStockOnHand, &$created) {
            $existingPivoted = ProductSku::query()
                ->where('product_id', $product->id)
                ->with('variantValues:id')
                ->get();

            $liveValueIdSets = [];

            foreach ($combinations as $combo) {
                /** @var list<ProductVariantValue> $combo */
                $valueIds = collect($combo)->pluck('id')->sort()->values()->all();
                $liveValueIdSets[] = $valueIds;

                $match = $existingPivoted->first(function (ProductSku $sku) use ($valueIds) {
                    $skuValueIds = $sku->variantValues->pluck('id')->sort()->values()->all();

                    return $valueIds === $skuValueIds;
                });

                if ($match) {
                    if (! $match->is_active) {
                        $match->update(['is_active' => true]);
                    }

                    continue;
                }

                $code = $this->buildSkuCode($product, $combo);
                $attributes = $this->buildAttributes($combo);

                $sku = ProductSku::query()->create([
                    'product_id' => $product->id,
                    'sku_code' => $this->uniqueSkuCode($code),
                    'price_amount' => $defaultPriceAmount,
                    'currency' => $currency,
                    'stock_on_hand' => $defaultStockOnHand,
                    'stock_reserved' => 0,
                    'is_active' => true,
                    'attributes' => $attributes,
                ]);

                $syncPayload = [];
                foreach ($combo as $v) {
                    $syncPayload[(int) $v->id] = ['product_variant_axis_id' => (int) $v->product_variant_axis_id];
                }
                $sku->variantValues()->sync($syncPayload);
                $created++;
            }

            // Any existing pivoted SKU whose value-set no longer appears in
            // the Cartesian product is orphaned. Never destructively remove
            // one with orders or editions.
            foreach ($existingPivoted as $sku) {
                $skuValueIds = $sku->variantValues->pluck('id')->sort()->values()->all();
                if ($skuValueIds === []) {
                    continue;
                }

                $stillReachable = collect($liveValueIdSets)->contains(fn (array $ids) => $ids === $skuValueIds);
                if ($stillReachable) {
                    continue;
                }

                if ($sku->orderItems()->exists() || $sku->editions()->exists()) {
                    if ($sku->is_active) {
                        $sku->update(['is_active' => false]);
                    }

                    continue;
                }

                $sku->variantValues()->detach();
                $sku->delete();
            }
        });

        return $created;
    }

    public function resolveSkuByAxisValues(Product $product, array $valueIds): ?ProductSku
    {
        $sorted = collect($valueIds)->map(fn ($v) => (int) $v)->sort()->values()->all();
        if ($sorted === []) {
            return null;
        }

        return ProductSku::query()
            ->where('product_id', $product->id)
            ->whereHas('variantValues', function ($q) use ($sorted) {
                $q->whereIn('product_variant_values.id', $sorted);
            }, '=', count($sorted))
            ->with('variantValues:id')
            ->get()
            ->first(function (ProductSku $sku) use ($sorted) {
                $skuValueIds = $sku->variantValues->pluck('id')->sort()->values()->all();

                return $sorted === $skuValueIds;
            });
    }

    /**
     * @param  array<int, list<ProductVariantValue>>  $groups
     * @return list<list<ProductVariantValue>>
     */
    private function cartesian(array $groups): array
    {
        $result = [[]];
        foreach ($groups as $group) {
            $next = [];
            foreach ($result as $prefix) {
                foreach ($group as $value) {
                    $next[] = array_merge($prefix, [$value]);
                }
            }
            $result = $next;
        }

        return $result;
    }

    /**
     * @param  list<ProductVariantValue>  $combo
     */
    private function buildSkuCode(Product $product, array $combo): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string) $product->slug) ?: 'PRD');
        $suffix = collect($combo)
            ->map(fn ($v) => strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string) $v->value)))
            ->implode('-');

        return $base.'-'.$suffix;
    }

    private function uniqueSkuCode(string $base): string
    {
        $candidate = $base;
        $i = 2;
        while (ProductSku::query()->where('sku_code', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }

    /**
     * @param  list<ProductVariantValue>  $combo
     * @return array<string, string>
     */
    private function buildAttributes(array $combo): array
    {
        $attrs = [];
        foreach ($combo as $value) {
            $axis = $value->relationLoaded('axis') ? $value->axis : $value->axis()->first();
            $attrs[(string) ($axis?->name ?: 'axis_'.$value->product_variant_axis_id)] = (string) $value->value;
        }

        return $attrs;
    }
}
