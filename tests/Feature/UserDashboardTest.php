<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_dashboard_displays_owned_editions(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->create(['artist_id' => $artist->id]);

        // Create some editions owned by the user
        $ownedEditions = ProductEdition::factory()
            ->count(3)
            ->create([
                'product_id' => $product->id,
                'owner_id' => $user->id,
            ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('UserDashboard')
            ->has('ownedEditions.data', 3)
        );
    }

    public function test_user_dashboard_shows_empty_state_when_no_editions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('UserDashboard')
            ->has('ownedEditions.data', 0)
        );
    }

    public function test_user_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_admin_user_sees_admin_panel_link(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('UserDashboard')
            ->where('auth.user.role', 'admin')
        );
    }

    public function test_artist_user_sees_artist_panel_link(): void
    {
        $artist = User::factory()->artist()->create();

        $response = $this->actingAs($artist)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('UserDashboard')
            ->where('auth.user.role', 'artist')
        );
    }

    public function test_regular_user_does_not_see_panel_link(): void
    {
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('UserDashboard')
            ->where('auth.user.role', 'user')
        );
    }
}
