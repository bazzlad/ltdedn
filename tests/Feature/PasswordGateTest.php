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

        config(['password_gate.enabled_in_tests' => true]);
        config(['password_gate.passwords.artist' => 'ARTIST']);
        config(['password_gate.passwords.invest' => 'INVEST']);
        config(['password_gate.routes.artist' => 'artist']);
        config(['password_gate.routes.invest' => 'invest']);
    }

    public function test_home_page_renders_password_gate(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PasswordGate')
            ->has('error')
        );
    }

    public function test_middleware_redirects_to_home_when_not_authenticated(): void
    {
        $response = $this->get('/artist');

        $response->assertRedirect('/');
    }

    public function test_middleware_allows_access_when_authenticated(): void
    {
        $this->session(['password_gate_artist_authenticated' => true]);

        $response = $this->get('/artist');

        $response->assertStatus(200);
    }

    public function test_middleware_allows_access_when_no_password_configured(): void
    {
        config(['password_gate.passwords.artist' => null]);

        $response = $this->get('/artist');

        $response->assertStatus(200);
    }

    public function test_artist_password_redirects_to_artists_page(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'ARTIST',
        ]);

        $response->assertRedirect(route('artist'));
        $response->assertSessionHas('password_gate_artist_authenticated', true);
    }

    public function test_invest_password_redirects_to_invest_page(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'INVEST',
        ]);

        $response->assertRedirect(route('invest'));
        $response->assertSessionHas('password_gate_invest_authenticated', true);
    }

    public function test_authentication_succeeds_case_insensitive(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'artist',
        ]);

        $response->assertRedirect(route('artist'));
        $response->assertSessionHas('password_gate_artist_authenticated', true);
    }

    public function test_authentication_fails_with_wrong_password(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas(config('password_gate.error_session_key'), 'Incorrect password');
    }

    public function test_authentication_requires_password(): void
    {
        $response = $this->post(route('password-gate.store'), []);

        $response->assertSessionHasErrors('password');
    }

    public function test_gates_are_independent(): void
    {
        $this->session(['password_gate_artist_authenticated' => true]);

        $response = $this->get('/invest');

        $response->assertRedirect('/');
    }
}
