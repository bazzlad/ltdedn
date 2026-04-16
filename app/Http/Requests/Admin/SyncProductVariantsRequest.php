<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncProductVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'axes' => ['present', 'array'],
            'axes.*.id' => ['nullable', 'integer'],
            'axes.*.name' => ['required', 'string', 'max:100'],
            'axes.*.sort_order' => ['nullable', 'integer'],
            'axes.*.values' => ['required', 'array', 'min:1'],
            'axes.*.values.*.id' => ['nullable', 'integer'],
            'axes.*.values.*.value' => ['required', 'string', 'max:100'],
            'axes.*.values.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
