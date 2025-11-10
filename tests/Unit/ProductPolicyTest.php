<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\User;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected ProductPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ProductPolicy;
    }

    public function test_admin_can_create_products_without_artist_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $result = $this->policy->create($admin);

        $this->assertTrue($result);
    }

    public function test_admin_can_create_products_with_artist_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();

        $result = $this->policy->create($admin, $artist->id);

        $this->assertTrue($result);
    }

    public function test_artist_can_create_products_when_they_have_owned_artists(): void
    {
        $user = User::factory()->create(['role' => UserRole::Artist]);
        Artist::factory()->create(['owner_id' => $user->id]);

        $result = $this->policy->create($user);

        $this->assertTrue($result);
    }

    public function test_artist_cannot_create_products_when_they_have_no_owned_artists(): void
    {
        $user = User::factory()->create(['role' => UserRole::Artist]);

        $result = $this->policy->create($user);

        $this->assertFalse($result);
    }

    public function test_artist_can_create_products_for_their_owned_artist(): void
    {
        $user = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $user->id]);

        $result = $this->policy->create($user, $ownedArtist->id);

        $this->assertTrue($result);
    }

    public function test_artist_cannot_create_products_for_unowned_artist(): void
    {
        $user = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $user->id]);
        $unownedArtist = Artist::factory()->create();

        $result = $this->policy->create($user, $unownedArtist->id);

        $this->assertFalse($result);
    }

    public function test_regular_user_cannot_create_products(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $result = $this->policy->create($user);

        $this->assertFalse($result);
    }

    public function test_can_manage_artist_product_with_null_artist_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::Artist]);

        $reflection = new \ReflectionClass($this->policy);
        $method = $reflection->getMethod('canManageArtistProduct');
        $method->setAccessible(true);

        $result = $method->invoke($this->policy, $user, null);

        $this->assertFalse($result);
    }

    public function test_can_manage_artist_product_with_valid_artist_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $user->id]);

        $reflection = new \ReflectionClass($this->policy);
        $method = $reflection->getMethod('canManageArtistProduct');
        $method->setAccessible(true);

        $result = $method->invoke($this->policy, $user, $artist->id);

        $this->assertTrue($result);
    }
}
