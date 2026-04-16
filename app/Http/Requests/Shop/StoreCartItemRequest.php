<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_sku_id' => ['nullable', 'integer', 'exists:product_skus,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function quantity(): int
    {
        return (int) ($this->input('quantity') ?? 1);
    }
}
