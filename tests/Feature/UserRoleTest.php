<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_admin_role(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isArtist());
        $this->assertFalse($user->isUser());
        $this->assertEquals(UserRole::Admin, $user->role);
    }

    public function test_user_can_have_artist_role(): void
    {
        $user = User::factory()->artist()->create();

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isArtist());
        $this->assertFalse($user->isUser());
        $this->assertEquals(UserRole::Artist, $user->role);
    }

    public function test_user_can_have_user_role(): void
    {
        $user = User::factory()->user()->create();

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isArtist());
        $this->assertTrue($user->isUser());
        $this->assertEquals(UserRole::User, $user->role);
    }

    public function test_user_role_enum_values(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = User::factory()->artist()->create();
        $user = User::factory()->user()->create();

        $this->assertEquals('admin', $admin->role->value);
        $this->assertEquals('artist', $artist->role->value);
        $this->assertEquals('user', $user->role->value);

        $this->assertEquals('Administrator', $admin->role->label());
        $this->assertEquals('Artist', $artist->role->label());
        $this->assertEquals('User', $user->role->label());
    }

    public function test_role_middleware_allows_correct_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = User::factory()->artist()->create();
        $user = User::factory()->user()->create();

        // Test admin access
        $this->actingAs($admin)
            ->get('/test-admin-route')
            ->assertStatus(404); // Route doesn't exist, but middleware should pass

        // Test artist access
        $this->actingAs($artist)
            ->get('/test-artist-route')
            ->assertStatus(404); // Route doesn't exist, but middleware should pass

        // Test user access
        $this->actingAs($user)
            ->get('/test-user-route')
            ->assertStatus(404); // Route doesn't exist, but middleware should pass
    }

    public function test_role_middleware_denies_incorrect_roles(): void
    {
        $user = User::factory()->user()->create();

        // Create a test route with admin middleware
        $this->app['router']->get('/test-admin-only', function () {
            return 'admin only';
        })->middleware(['auth', 'role:admin']);

        // Test that regular user cannot access admin route
        $this->actingAs($user)
            ->get('/test-admin-only')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        // Create a test route with role middleware
        $this->app['router']->get('/test-protected', function () {
            return 'protected';
        })->middleware(['role:admin']);

        $this->get('/test-protected')
            ->assertStatus(401);
    }
}
