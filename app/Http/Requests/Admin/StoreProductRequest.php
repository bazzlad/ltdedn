<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'artist_id' => ['required', 'exists:artists,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'cover_image_url' => ['nullable', 'url', 'max:500'],
            'sell_through_ltdedn' => ['boolean'],
            'is_limited' => ['boolean'],
            'edition_size' => ['nullable', 'integer', 'min:1'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'is_public' => ['boolean'],
            'collection_id' => ['nullable', 'exists:collections,id'],
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
            'collection_id.exists' => 'The selected collection is invalid.',
        ];
    }
}
