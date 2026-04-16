<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:250'],
        ];
    }

    public function amountMinor(): int
    {
        return (int) ($this->input('amount_minor') ?? 0);
    }
}
