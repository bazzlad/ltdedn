<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_access_users_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->component('Admin/Users/Index')
                ->has('users');
        });
    }

    public function test_admin_can_access_user_create_form(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/admin/users/create');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->component('Admin/Users/Create')
                ->has('roles');
        });
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::User->value,
        ];

        $response = $this->actingAs($admin)->post('/admin/users', $userData);

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => UserRole::User->value,
        ]);
    }

    public function test_admin_can_view_user_details(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($admin)->get("/admin/users/{$user->id}");

        $response->assertOk();
        $response->assertInertia(function ($page) use ($user) {
            $page->component('Admin/Users/Show')
                ->has('user')
                ->where('user.id', $user->id)
                ->where('user.name', $user->name)
                ->where('user.email', $user->email);
        });
    }

    public function test_admin_can_access_user_edit_form(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($admin)->get("/admin/users/{$user->id}/edit");

        $response->assertOk();
        $response->assertInertia(function ($page) use ($user) {
            $page->component('Admin/Users/Edit')
                ->has('user')
                ->has('roles')
                ->where('user.id', $user->id)
                ->where('user.name', $user->name)
                ->where('user.email', $user->email);
        });
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::User]);

        $updateData = [
            'name' => 'Updated User Name',
            'email' => 'updated@example.com',
            'role' => UserRole::Artist->value,
        ];

        $response = $this->actingAs($admin)->put("/admin/users/{$user->id}", $updateData);

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User Name',
            'email' => 'updated@example.com',
            'role' => UserRole::Artist->value,
        ]);
    }

    public function test_admin_can_update_user_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::User]);
        $originalPassword = $user->password;

        $updateData = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
            'role' => $user->role->value,
        ];

        $response = $this->actingAs($admin)->put("/admin/users/{$user->id}", $updateData);

        $response->assertRedirect('/admin/users');

        $user->refresh();
        $this->assertNotEquals($originalPassword, $user->password);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $userToDelete = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($admin)->delete("/admin/users/{$userToDelete->id}");

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_cannot_access_users_index(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::User->value,
        ];

        $response = $this->actingAs($user)->post('/admin/users', $userData);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_update_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $otherUser = User::factory()->create(['role' => UserRole::User]);

        $updateData = [
            'name' => 'Updated User Name',
            'email' => 'updated@example.com',
            'role' => UserRole::Artist->value,
        ];

        $response = $this->actingAs($user)->put("/admin/users/{$otherUser->id}", $updateData);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_delete_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $otherUser = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($user)->delete("/admin/users/{$otherUser->id}");

        $response->assertForbidden();
    }

    public function test_user_creation_requires_valid_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->post('/admin/users', []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    }

    public function test_user_creation_requires_unique_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $existingUser = User::factory()->create();

        $userData = [
            'name' => 'Test User',
            'email' => $existingUser->email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::User->value,
        ];

        $response = $this->actingAs($admin)->post('/admin/users', $userData);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_user_update_requires_valid_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => '',
            'email' => 'invalid-email',
            'role' => 'invalid-role',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'role']);
    }
}
