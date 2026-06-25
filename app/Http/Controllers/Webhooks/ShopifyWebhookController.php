<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\StorefrontPlatform;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessExternalOrderWebhook;
use App\Models\StorefrontConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopifyWebhookController extends Controller
{
    public function __invoke(Request $request, StorefrontConnection $connection): JsonResponse
    {
        abort_unless($this->platformValue($connection) === StorefrontPlatform::Shopify->value, 404);
        abort_unless($this->hasValidSignature($request, $connection), 401);

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        ProcessExternalOrderWebhook::dispatch(
            $connection->id,
            StorefrontPlatform::Shopify->value,
            $payload,
            $request->header('X-Shopify-Webhook-Id') ?: $request->header('X-Shopify-Event-Id'),
        );

        return response()->json([
            'status' => 'queued',
        ]);
    }

    private function hasValidSignature(Request $request, StorefrontConnection $connection): bool
    {
        $signature = (string) $request->header('X-Shopify-Hmac-Sha256', '');
        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $connection->webhook_secret, true));

        return $signature !== '' && hash_equals($expected, $signature);
    }

    private function platformValue(StorefrontConnection $connection): string
    {
        return $connection->platform instanceof StorefrontPlatform
            ? $connection->platform->value
            : (string) $connection->platform;
    }
}
