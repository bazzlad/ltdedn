<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordGateController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('PasswordGate', [
            'error' => session(config('password_gate.error_session_key')),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $password = $request->input('password');
        $passwords = config('password_gate.passwords', []);
        $routes = config('password_gate.routes', []);

        foreach ($passwords as $gate => $configuredPassword) {
            if (strcasecmp($password, $configuredPassword) === 0) {
                session(["password_gate_{$gate}_authenticated" => true]);

                $redirectRoute = $routes[$gate] ?? $gate;

                return redirect()->route($redirectRoute);
            }
        }

        return redirect()
            ->route('home')
            ->with(config('password_gate.error_session_key'), 'Incorrect password');
    }
}
