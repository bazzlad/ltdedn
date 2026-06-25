<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\StorefrontPlatform;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessExternalOrderWebhook;
use App\Models\StorefrontConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SquarespaceWebhookController extends Controller
{
    public function __invoke(Request $request, StorefrontConnection $connection): JsonResponse
    {
        abort_unless($this->platformValue($connection) === StorefrontPlatform::Squarespace->value, 404);
        abort_unless($this->hasValidSignature($request, $connection), 401);

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        ProcessExternalOrderWebhook::dispatch(
            $connection->id,
            StorefrontPlatform::Squarespace->value,
            $payload,
            $request->header('X-Squarespace-Webhook-Id') ?: $request->header('X-Squarespace-Event-Id'),
        );

        return response()->json([
            'status' => 'queued',
        ]);
    }

    private function hasValidSignature(Request $request, StorefrontConnection $connection): bool
    {
        $signature = (string) (
            $request->header('X-Squarespace-Signature')
            ?: $request->header('X-Squarespace-Hmac-Sha256')
            ?: ''
        );

        foreach ($this->webhookSecretKeys((string) $connection->webhook_secret) as $key) {
            $binary = hash_hmac('sha256', $request->getContent(), $key, true);
            $base64 = base64_encode($binary);
            $hex = hash_hmac('sha256', $request->getContent(), $key);

            if ($signature !== '' && (hash_equals($base64, $signature) || hash_equals($hex, $signature))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function webhookSecretKeys(string $secret): array
    {
        $keys = [$secret];

        if (strlen($secret) % 2 === 0 && ctype_xdigit($secret)) {
            $binary = hex2bin($secret);

            if ($binary !== false) {
                array_unshift($keys, $binary);
            }
        }

        return array_values(array_unique($keys));
    }

    private function platformValue(StorefrontConnection $connection): string
    {
        return $connection->platform instanceof StorefrontPlatform
            ? $connection->platform->value
            : (string) $connection->platform;
    }
}
