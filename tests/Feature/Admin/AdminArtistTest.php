<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArtistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_access_artists_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/admin/artists');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->component('Admin/Artists/Index')
                ->has('artists');
        });
    }

    public function test_admin_can_access_artist_create_form(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/admin/artists/create');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->component('Admin/Artists/Create')
                ->has('users');
        });
    }

    public function test_admin_can_create_artist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);

        $artistData = [
            'name' => 'Test Artist',
            'owner_id' => $owner->id,
        ];

        $response = $this->actingAs($admin)->post('/admin/artists', $artistData);

        $response->assertRedirect('/admin/artists');
        $this->assertDatabaseHas('artists', [
            'name' => 'Test Artist',
            'owner_id' => $owner->id,
        ]);
    }

    public function test_admin_can_view_artist_details(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->get("/admin/artists/{$artist->id}");

        $response->assertOk();
        $response->assertInertia(function ($page) use ($artist) {
            $page->component('Admin/Artists/Show')
                ->has('artist')
                ->where('artist.id', $artist->id)
                ->where('artist.name', $artist->name)
                ->where('artist.slug', $artist->slug);
        });
    }

    public function test_admin_can_access_artist_edit_form(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->get("/admin/artists/{$artist->id}/edit");

        $response->assertOk();
        $response->assertInertia(function ($page) use ($artist) {
            $page->component('Admin/Artists/Edit')
                ->has('artist')
                ->has('users')
                ->where('artist.id', $artist->id)
                ->where('artist.name', $artist->name)
                ->where('artist.slug', $artist->slug);
        });
    }

    public function test_admin_can_update_artist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $newOwner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $updateData = [
            'name' => 'Updated Artist Name',
            'slug' => 'updated-artist-slug',
            'owner_id' => $newOwner->id,
        ];

        $response = $this->actingAs($admin)->put("/admin/artists/{$artist->id}", $updateData);

        $response->assertRedirect('/admin/artists');
        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'name' => 'Updated Artist Name',
            'slug' => 'updated-artist-slug',
            'owner_id' => $newOwner->id,
        ]);
    }

    public function test_admin_can_delete_artist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->delete("/admin/artists/{$artist->id}");

        $response->assertRedirect('/admin/artists');
        $this->assertDatabaseMissing('artists', ['id' => $artist->id]);
    }

    public function test_non_admin_cannot_access_artists_index(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($user)->get('/admin/artists');

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_create_artist(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);

        $artistData = [
            'name' => 'Test Artist',
            'owner_id' => $owner->id,
        ];

        $response = $this->actingAs($user)->post('/admin/artists', $artistData);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_update_artist(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $updateData = [
            'name' => 'Updated Artist Name',
            'slug' => 'updated-artist-slug',
            'owner_id' => $owner->id,
        ];

        $response = $this->actingAs($user)->put("/admin/artists/{$artist->id}", $updateData);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_delete_artist(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($user)->delete("/admin/artists/{$artist->id}");

        $response->assertForbidden();
    }

    public function test_artist_creation_requires_valid_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->post('/admin/artists', []);

        $response->assertSessionHasErrors(['name', 'owner_id']);
    }

    public function test_artist_creation_requires_existing_owner(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $artistData = [
            'name' => 'Test Artist',
            'owner_id' => 99999, // Non-existent user ID
        ];

        $response = $this->actingAs($admin)->post('/admin/artists', $artistData);

        $response->assertSessionHasErrors(['owner_id']);
    }

    public function test_artist_creation_generates_slug_automatically(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);

        $artistData = [
            'name' => 'Test Artist Name',
            'owner_id' => $owner->id,
        ];

        $response = $this->actingAs($admin)->post('/admin/artists', $artistData);

        $response->assertRedirect('/admin/artists');
        $this->assertDatabaseHas('artists', [
            'name' => 'Test Artist Name',
            'slug' => 'test-artist-name',
            'owner_id' => $owner->id,
        ]);
    }

    public function test_artist_slug_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $existingArtist = Artist::factory()->create([
            'slug' => 'test-artist',
            'owner_id' => $owner->id,
        ]);

        $artistData = [
            'name' => 'Test Artist',
            'slug' => 'test-artist',
            'owner_id' => $owner->id,
        ];

        $response = $this->actingAs($admin)->post('/admin/artists', $artistData);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_artist_update_requires_valid_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->put("/admin/artists/{$artist->id}", [
            'name' => '',
            'slug' => '',
            'owner_id' => 99999,
        ]);

        $response->assertSessionHasErrors(['name', 'owner_id']);
    }

    public function test_artist_update_slug_uniqueness_ignores_current_artist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create([
            'slug' => 'test-artist',
            'owner_id' => $owner->id,
        ]);

        $updateData = [
            'name' => 'Updated Artist Name',
            'slug' => 'test-artist', // Same slug should be allowed for the same artist
            'owner_id' => $owner->id,
        ];

        $response = $this->actingAs($admin)->put("/admin/artists/{$artist->id}", $updateData);

        $response->assertRedirect('/admin/artists');
        $response->assertSessionHasNoErrors();
    }
}
