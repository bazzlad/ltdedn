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
            'gate' => $request->input('gate', 'default'),
            'error' => session(config('password_gate.error_session_key')),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'intended' => 'required|string',
            'gate' => 'required|string',
        ]);

        $password = $request->input('password');
        $intended = $request->input('intended');
        $gate = $request->input('gate');
        $configuredPassword = config("password_gate.passwords.{$gate}");

        if ($configuredPassword && strcasecmp($password, $configuredPassword) === 0) {
            session(["password_gate_{$gate}_authenticated" => true]);

            return redirect($intended);
        }

        return redirect()
            ->route('password-gate.show', ['intended' => $intended, 'gate' => $gate])
            ->with(config('password_gate.error_session_key'), 'Incorrect password');
    }
}
