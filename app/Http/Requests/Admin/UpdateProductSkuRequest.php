<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductSkuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sku = $this->route('sku');
        $skuId = $sku instanceof ProductSku ? $sku->id : null;

        return [
            'sku_code' => ['required', 'string', 'max:64', 'unique:product_skus,sku_code,'.$skuId],
            'price_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'in:gbp'],
            'stock_on_hand' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'attributes' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = $this->route('product');
            if (! $product instanceof Product) {
                return;
            }

            $attributes = $this->input('attributes', []);
            if (! is_array($attributes) || $attributes === []) {
                return;
            }

            $axes = $product->variantAxes()->with('values')->get();
            if ($axes->isEmpty()) {
                return;
            }

            foreach ($attributes as $key => $value) {
                $axis = $axes->first(function ($axis) use ($key) {
                    return strtolower((string) $axis->name) === strtolower((string) $key);
                });

                if (! $axis) {
                    $validator->errors()->add('attributes', 'Unknown variant axis: '.$key);
                    continue;
                }

                $valid = $axis->values->contains(function ($v) use ($value) {
                    return strtolower((string) $v->value) === strtolower((string) $value);
                });

                if (! $valid) {
                    $validator->errors()->add('attributes', 'Invalid value for '.$key.': '.$value);
                }
            }
        });
    }
}
