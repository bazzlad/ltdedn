<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductEditionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkProductEditionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $routeProduct = $this->route('product');
        $productId = $routeProduct instanceof \App\Models\Product ? $routeProduct->id : $routeProduct;

        return [
            'product_sku_id' => [
                'nullable',
                Rule::exists('product_skus', 'id')->where(function ($query) use ($productId) {
                    return $query->where('product_id', $productId);
                }),
            ],
            'start_number' => [
                'required',
                'integer',
                'min:1',
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],
            'status' => ['required', Rule::enum(ProductEditionStatus::class)],
            'owner_id' => ['nullable', 'exists:users,id'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_sku_id.exists' => 'Selected SKU is invalid for this product.',
            'start_number.required' => 'Starting number is required.',
            'start_number.integer' => 'Starting number must be a valid number.',
            'start_number.min' => 'Starting number must be at least 1.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a valid number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity cannot exceed 1000 editions at once.',
            'status.required' => 'Status is required.',
            'status.enum' => 'Invalid status selected.',
            'owner_id.exists' => 'Selected owner is invalid.',
        ];
    }
}
