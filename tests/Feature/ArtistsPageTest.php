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
        $response = $this->get('/artist');

        $response->assertRedirect('/');
    }

    public function test_artists_page_shows_error_with_wrong_password(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas(config('password_gate.error_session_key'), 'Incorrect password');
    }

    public function test_artists_page_allows_access_with_correct_password(): void
    {
        $response = $this->post(route('password-gate.store'), [
            'password' => 'ARTIST',
            'intended' => '/artist',
            'gate' => 'artist',
        ]);

        $response->assertRedirect('/artist');

        // Follow the redirect and check we can now access the page
        $response = $this->get('/artist');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Artists'));
    }
}
