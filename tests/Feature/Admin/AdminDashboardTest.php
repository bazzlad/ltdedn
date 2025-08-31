<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->component('Admin/Dashboard')
                ->has('stats')
                ->has('stats.total_users')
                ->has('stats.total_artists')
                ->has('stats.recent_users')
                ->has('stats.recent_artists');
        });
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    public function test_artist_can_access_dashboard(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);

        $response = $this->actingAs($artist)->get('/admin');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->component('Admin/Dashboard')
                ->has('stats')
                ->has('stats.total_artists')
                ->has('stats.recent_artists');
        });
    }
}
