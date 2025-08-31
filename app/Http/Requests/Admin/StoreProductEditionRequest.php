<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductEditionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('product_editions')->where(function ($query) use ($productId) {
                    return $query->where('product_id', $productId);
                }),
            ],
            'status' => ['required', 'in:available,sold,redeemed,pending_transfer,invalidated'],
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
            'number.required' => 'Edition number is required.',
            'number.integer' => 'Edition number must be a valid number.',
            'number.min' => 'Edition number must be at least 1.',
            'number.unique' => 'This edition number already exists for this product.',
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status selected.',
            'owner_id.exists' => 'Selected owner is invalid.',
        ];
    }
}
