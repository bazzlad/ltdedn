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
            'intended' => $request->input('intended', '/'),
            'error' => session(config('password_gate.error_session_key')),
        ]);
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'intended' => 'required|string',
        ]);

        $password = $request->input('password');
        $intended = $request->input('intended');
        $configuredPassword = config('password_gate.password');

        if ($password === $configuredPassword) {
            // Mark as authenticated in session
            session([config('password_gate.session_key') => true]);

            // Redirect to intended URL
            return redirect($intended);
        }

        // Store error and redirect back to gate
        return redirect()
            ->route('password-gate', ['intended' => $intended])
            ->with(config('password_gate.error_session_key'), 'Incorrect password');
    }
}
