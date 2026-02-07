<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable password gate for these tests and set test passwords
        config(['password_gate.enabled_in_tests' => true]);
        config(['password_gate.passwords.artists' => 'ARTIST']);
        config(['password_gate.passwords.invest' => 'INVEST']);
    }

    public function test_middleware_redirects_to_password_gate_when_not_authenticated(): void
    {
        $response = $this->get('/artists');

        $response->assertRedirectContains('/password-gate');
        $response->assertRedirectContains('intended=');
        $response->assertRedirectContains('gate=artists');
    }

    public function test_middleware_allows_access_when_authenticated(): void
    {
        $this->session(['password_gate_artists_authenticated' => true]);

        $response = $this->get('/artists');

        $response->assertStatus(200);
    }

    public function test_middleware_allows_access_when_no_password_configured(): void
    {
        config(['password_gate.passwords.artists' => null]);

        $response = $this->get('/artists');

        $response->assertStatus(200);
    }

    public function test_password_gate_renders_correctly(): void
    {
        $response = $this->get(route('password-gate.show', ['intended' => '/artists', 'gate' => 'artists']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PasswordGate')
            ->has('intended')
            ->has('gate')
            ->has('error')
        );
    }

    public function test_authentication_succeeds_with_correct_password(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'ARTIST',
            'intended' => '/artists',
            'gate' => 'artists',
        ]);

        $response->assertRedirect('/artists');
        $response->assertSessionHas('password_gate_artists_authenticated', true);
    }

    public function test_authentication_succeeds_case_insensitive(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'artist',
            'intended' => '/artists',
            'gate' => 'artists',
        ]);

        $response->assertRedirect('/artists');
        $response->assertSessionHas('password_gate_artists_authenticated', true);
    }

    public function test_authentication_fails_with_wrong_password(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'wrong-password',
            'intended' => '/artists',
            'gate' => 'artists',
        ]);

        $response->assertRedirectContains('/password-gate');
        $response->assertSessionHas(config('password_gate.error_session_key'), 'Incorrect password');
        $response->assertSessionMissing('password_gate_artists_authenticated');
    }

    public function test_authentication_requires_password(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'intended' => '/artists',
            'gate' => 'artists',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_authentication_requires_intended_url(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'ARTIST',
            'gate' => 'artists',
        ]);

        $response->assertSessionHasErrors('intended');
    }

    public function test_invest_gate_uses_separate_password(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'invest',
            'intended' => '/invest',
            'gate' => 'invest',
        ]);

        $response->assertRedirect('/invest');
        $response->assertSessionHas('password_gate_invest_authenticated', true);
    }

    public function test_gates_are_independent(): void
    {
        // Authenticate for artists gate
        $this->session(['password_gate_artists_authenticated' => true]);

        // Should still be blocked on invest
        $response = $this->get('/invest');

        $response->assertRedirectContains('/password-gate');
        $response->assertRedirectContains('gate=invest');
    }
}
