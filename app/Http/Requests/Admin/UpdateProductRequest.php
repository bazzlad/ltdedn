<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'artist_id' => (int) $this->artist_id,
            'cover_image_url' => $this->cover_image_url === '' ? null : $this->cover_image_url,
            'sell_through_ltdedn' => $this->boolean('sell_through_ltdedn'),
            'is_limited' => $this->boolean('is_limited'),
            'is_public' => $this->boolean('is_public'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product?->getKey();

        return [
            'artist_id' => ['required', 'exists:artists,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'description' => ['nullable', 'string'],
            'cover_image_url' => ['nullable', 'url', 'max:500'],
            'sell_through_ltdedn' => ['boolean'],
            'is_limited' => ['boolean'],
            'edition_size' => ['nullable', 'integer', 'min:1'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'is_public' => ['boolean'],
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
            'artist_id.required' => 'Please select an artist.',
            'artist_id.exists' => 'The selected artist is invalid.',
            'name.required' => 'The product name is required.',
            'slug.unique' => 'This slug is already taken.',
            'cover_image_url.url' => 'The cover image must be a valid URL.',
            'edition_size.integer' => 'Edition size must be a number.',
            'edition_size.min' => 'Edition size must be at least 1.',
            'base_price.numeric' => 'The base price must be a valid number.',
            'base_price.min' => 'The base price must be at least 0.',
        ];
    }
}
