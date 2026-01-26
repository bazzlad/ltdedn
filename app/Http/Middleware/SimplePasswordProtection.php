<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SimplePasswordProtection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $password): Response
    {
        $sessionKey = 'password_protected_'.md5($password);

        if (session($sessionKey)) {
            return $next($request);
        }

        if ($request->method() === 'POST') {
            if ($request->input('password') === $password) {
                session([$sessionKey => true]);

                return redirect($request->url());
            }

            // Show error on the same page for wrong password
            return Inertia::render('PasswordProtected', [
                'action' => $request->url(),
                'error' => 'Incorrect password',
            ])->toResponse($request);
        }

        return Inertia::render('PasswordProtected', [
            'action' => $request->url(),
            'error' => null,
        ])->toResponse($request);
    }
}
