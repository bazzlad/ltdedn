<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArtistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('artists')->ignore($this->artist)],
            'owner_id' => ['required', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The artist name is required.',
            'slug.unique' => 'This slug is already taken.',
            'owner_id.required' => 'Please select an owner for this artist.',
            'owner_id.exists' => 'The selected owner does not exist.',
        ];
    }
}
