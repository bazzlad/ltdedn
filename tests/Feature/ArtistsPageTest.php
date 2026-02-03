<?php

namespace Tests\Feature;

use Tests\TestCase;

class ArtistsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable password gate for these tests
        config(['password_gate.enabled_in_tests' => true]);
    }

    public function test_artists_page_requires_password(): void
    {
        $response = $this->get('/artists');

        $response->assertRedirectContains('/password-gate');
        $response->assertRedirectContains('intended=');
    }

    public function test_artists_page_shows_error_with_wrong_password(): void
    {
        $response = $this->post(route('password-gate.authenticate'), [
            'password' => 'wrongpassword',
            'intended' => '/artists',
        ]);

        $response->assertRedirectContains('/password-gate');
        $response->assertSessionHas(config('password_gate.error_session_key'), 'Incorrect password');
    }

    public function test_artists_page_allows_access_with_correct_password(): void
    {
        $response = $this->post(route('password-gate.authenticate'), [
            'password' => config('password_gate.password'),
            'intended' => '/artists',
        ]);

        $response->assertRedirect('/artists');

        // Follow the redirect and check we can now access the page
        $response = $this->get('/artists');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Artists'));
    }
}
