<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimplePasswordProtection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $gate = 'default'): Response
    {
        // Skip protection during tests unless explicitly enabled
        if (app()->runningUnitTests() && ! config('password_gate.enabled_in_tests')) {
            return $next($request);
        }

        // Skip protection if no password is configured for this gate
        if (! config("password_gate.passwords.{$gate}")) {
            return $next($request);
        }

        // Check if already authenticated for this gate
        if (session("password_gate_{$gate}_authenticated")) {
            return $next($request);
        }

        // Redirect to password gate with intended URL and gate name
        return redirect()->route('password-gate.show', [
            'intended' => $request->fullUrl(),
            'gate' => $gate,
        ]);
    }
}
