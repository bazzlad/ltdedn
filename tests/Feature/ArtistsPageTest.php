<?php

namespace Tests\Feature;

use Tests\TestCase;

class ArtistsPageTest extends TestCase
{
    public function test_artists_page_requires_password(): void
    {
        $response = $this->get('/artists');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('PasswordProtected')
            ->has('action')
            ->where('error', null)
        );
    }

    public function test_artists_page_shows_error_with_wrong_password(): void
    {
        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->post('/artists', ['password' => 'wrongpassword']);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('PasswordProtected')
            ->has('action')
            ->where('error', 'Incorrect password')
        );
    }

    public function test_artists_page_allows_access_with_correct_password(): void
    {
        // Initialize session first
        $this->withSession([]);

        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->post('/artists', ['password' => 'artists123']);

        $response->assertRedirect('/artists');

        // Follow the redirect and check we can now access the page
        $response = $this->get('/artists');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Artists'));
    }
}
