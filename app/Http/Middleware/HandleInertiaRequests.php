<?php

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'cartSummary' => fn () => $this->cartSummary($request),
            'fulfilmentQueueCount' => fn () => $this->fulfilmentQueueCount($request),
        ];
    }

    /**
     * Number of paid orders awaiting shipment. Drives the sidebar "Fulfilment"
     * badge so admins see at a glance whether anything's waiting.
     */
    private function fulfilmentQueueCount(Request $request): int
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return 0;
        }

        return (int) Order::query()
            ->where('status', OrderStatus::Paid)
            ->whereNull('shipped_at')
            ->count();
    }

    /**
     * @return array{item_count: int, subtotal: int, currency: string}
     */
    private function cartSummary(Request $request): array
    {
        if (! $request->user() && ! $request->hasCookie(CartService::COOKIE_NAME)) {
            return ['item_count' => 0, 'subtotal' => 0, 'currency' => 'gbp'];
        }

        /** @var CartService $service */
        $service = app(CartService::class);
        $cart = $service->resolveForRequest($request);
        $snapshot = $service->pricingSnapshot($cart);

        return [
            'item_count' => $snapshot['item_count'],
            'subtotal' => $snapshot['subtotal'],
            'currency' => $snapshot['currency'],
        ];
    }
}
