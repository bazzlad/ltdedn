<?php

namespace App\Listeners;

use App\Models\Cart;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Events\Login;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

class MergeGuestCartOnLogin
{
    public function __construct(private CartService $cartService) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        // Resolve the request lazily — the listener may run under a console
        // or queued context where no HTTP request is in scope. Without a
        // request there's no guest-cart cookie to merge, so exit quietly.
        $container = Container::getInstance();
        if (! $container->bound('request')) {
            return;
        }

        $request = $container->make('request');
        if (! $request instanceof Request) {
            return;
        }

        $token = (string) $request->cookie(CartService::COOKIE_NAME, '');
        if ($token === '') {
            return;
        }

        $guest = Cart::query()
            ->whereNull('user_id')
            ->where('session_token', $token)
            ->first();

        if (! $guest) {
            return;
        }

        $userCart = Cart::query()->where('user_id', $user->id)->first();

        if (! $userCart) {
            $guest->update([
                'user_id' => $user->id,
                'session_token' => null,
                'expires_at' => null,
            ]);

            return;
        }

        $this->cartService->mergeGuestIntoUser($guest, $userCart);
    }
}
