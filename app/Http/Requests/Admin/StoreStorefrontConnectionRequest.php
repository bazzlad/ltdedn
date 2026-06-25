<?php

namespace App\Http\Requests\Admin;

use App\Enums\StorefrontConnectionStatus;
use App\Enums\StorefrontPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStorefrontConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'artist_id' => ['required', 'exists:artists,id'],
            'platform' => ['required', Rule::enum(StorefrontPlatform::class)],
            'name' => ['required', 'string', 'max:255'],
            'store_url' => ['nullable', 'url', 'max:255'],
            'external_shop_id' => ['nullable', 'string', 'max:255'],
            'external_shop_domain' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:4096'],
            'refresh_token' => ['nullable', 'string', 'max:4096'],
            'webhook_secret' => ['nullable', 'string', 'max:4096'],
            'connection_status' => ['required', Rule::enum(StorefrontConnectionStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'artist_id.required' => 'Please choose the artist this store belongs to.',
            'platform.required' => 'Please choose Shopify, Squarespace, or Order Desk.',
            'name.required' => 'Please name this connection.',
            'store_url.url' => 'Enter the full store URL, including https://.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (
                    $this->input('platform') === StorefrontPlatform::Shopify->value
                    && blank($this->input('webhook_secret'))
                    && blank(config('services.shopify_connect.client_secret'))
                ) {
                    $validator->errors()->add('webhook_secret', 'Enter the Shopify app secret before saving this connection.');
                }

                if ($this->input('platform') !== StorefrontPlatform::OrderDesk->value) {
                    return;
                }

                if (blank($this->input('external_shop_id'))) {
                    $validator->errors()->add('external_shop_id', 'Enter the Order Desk store ID.');
                }

                if (blank($this->input('access_token'))) {
                    $validator->errors()->add('access_token', 'Enter the Order Desk API key.');
                }
            },
        ];
    }
}
