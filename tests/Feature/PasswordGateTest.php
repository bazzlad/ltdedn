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

        // Enable password gate for these tests and set a test password
        config(['password_gate.enabled_in_tests' => true]);
        config(['password_gate.password' => 'test-password']);
    }

    public function test_middleware_redirects_to_password_gate_when_not_authenticated(): void
    {
        $response = $this->get('/artists');

        $response->assertRedirectContains('/password-gate');
        $response->assertRedirectContains('intended=');
    }

    public function test_middleware_allows_access_when_authenticated(): void
    {
        // Simulate authenticated session
        $this->session([config('password_gate.session_key') => true]);

        $response = $this->get('/artists');

        $response->assertStatus(200);
    }

    public function test_middleware_allows_access_when_no_password_configured(): void
    {
        config(['password_gate.password' => null]);

        $response = $this->get('/artists');

        $response->assertStatus(200);
    }

    public function test_password_gate_renders_correctly(): void
    {
        $response = $this->get(route('password-gate', ['intended' => '/artists']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PasswordGate')
            ->has('intended')
            ->has('error')
        );
    }

    public function test_authentication_succeeds_with_correct_password(): void
    {
        $response = $this->post(route('password-gate.authenticate'), [
            'password' => 'test-password',
            'intended' => '/artists',
        ]);

        $response->assertRedirect('/artists');
        $response->assertSessionHas(config('password_gate.session_key'), true);
    }

    public function test_authentication_fails_with_wrong_password(): void
    {
        $response = $this->post(route('password-gate.authenticate'), [
            'password' => 'wrong-password',
            'intended' => '/artists',
        ]);

        $response->assertRedirectContains('/password-gate');
        $response->assertSessionHas(config('password_gate.error_session_key'), 'Incorrect password');
        $response->assertSessionMissing(config('password_gate.session_key'));
    }

    public function test_authentication_requires_password(): void
    {
        $response = $this->post(route('password-gate.authenticate'), [
            'intended' => '/artists',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_authentication_requires_intended_url(): void
    {
        $response = $this->post(route('password-gate.authenticate'), [
            'password' => 'test-password',
        ]);

        $response->assertSessionHasErrors('intended');
    }
}
