<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
        $productId = $this->route('product');

        return [
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
            'start_number.required' => 'Starting number is required.',
            'start_number.integer' => 'Starting number must be a valid number.',
            'start_number.min' => 'Starting number must be at least 1.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a valid number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity cannot exceed 1000 editions at once.',
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status selected.',
            'owner_id.exists' => 'Selected owner is invalid.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $productId = $this->route('product');
            $startNumber = $this->input('start_number');
            $quantity = $this->input('quantity');

            if ($startNumber && $quantity) {
                $endNumber = $startNumber + $quantity - 1;

                // Check if any numbers in the range already exist
                $existingNumbers = \App\Models\ProductEdition::where('product_id', $productId)
                    ->whereBetween('number', [$startNumber, $endNumber])
                    ->pluck('number')
                    ->toArray();

                if (! empty($existingNumbers)) {
                    $validator->errors()->add(
                        'start_number',
                        'Edition numbers '.implode(', ', $existingNumbers).' already exist for this product.'
                    );
                }
            }
        });
    }
}
