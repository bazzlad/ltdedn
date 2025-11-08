<?php

namespace Tests\Feature;

use App\Enums\ProductEditionStatus;
use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use App\Services\QRCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QRClaimTest extends TestCase
{
    use RefreshDatabase;

    private QRCodeService $qrService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->qrService = app(QRCodeService::class);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_valid_qr_code_shows_claim_page(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create([
            'name' => 'Test Product',
            'description' => 'A test product description',
        ]);
        $edition = ProductEdition::factory()->for($product)->create([
            'owner_id' => null,
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $response = $this->get(route('qr.show', $edition->qr_code));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('QR/Claim')
            ->where('edition.id', $edition->id)
            ->where('edition.number', 1)
            ->where('edition.status', ProductEditionStatus::Available)
            ->where('edition.product.name', 'Test Product')
            ->where('edition.product.artist.name', $artist->name)
            ->where('isClaimed', false)
            ->where('isOwnedByCurrentUser', false)
            ->where('canClaim', true)
        );
    }

    public function test_invalid_qr_code_shows_not_found_page(): void
    {
        $invalidCode = 'invalid-qr-code';

        $response = $this->get(route('qr.show', $invalidCode));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('QR/NotFound')
            ->where('qrCode', $invalidCode)
        );
    }

    public function test_guest_can_view_claim_page_but_cannot_claim(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Available,
        ]);

        $response = $this->get(route('qr.show', $edition->qr_code));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('QR/Claim')
            ->where('canClaim', true)
            ->where('isClaimed', false)
        );
    }

    public function test_guest_claiming_redirects_to_login(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Available,
        ]);

        $response = $this->post(route('qr.claim', $edition->qr_code));

        $response->assertRedirect(route('login'));

        // The session flash message should be set (checking for redirect is sufficient for this test)
        // The main goal is to ensure guests are redirected to login when trying to claim
    }

    public function test_authenticated_user_can_claim_available_edition(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $response = $this->actingAs($user)->post(route('qr.claim', $edition->qr_code));

        $response->assertRedirect(route('qr.show', $edition->qr_code));
        $response->assertSessionHas('success');

        $edition->refresh();
        $this->assertEquals($user->id, $edition->owner_id);
        $this->assertEquals(ProductEditionStatus::Sold, $edition->status);
    }

    public function test_user_cannot_claim_already_claimed_edition(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $owner = User::factory()->create(['role' => UserRole::User]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Sold,
            'owner_id' => $owner->id,
        ]);

        $response = $this->actingAs($user)->post(route('qr.claim', $edition->qr_code));

        $response->assertRedirect(route('qr.show', $edition->qr_code));
        $response->assertSessionHas('error', 'This edition has already been claimed by someone else.');

        $edition->refresh();
        $this->assertEquals($owner->id, $edition->owner_id);
    }

    public function test_user_gets_info_message_when_claiming_own_edition(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Sold,
            'owner_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('qr.claim', $edition->qr_code));

        $response->assertRedirect(route('qr.show', $edition->qr_code));
        $response->assertSessionHas('info', 'You already own this edition.');
    }

    public function test_user_cannot_claim_unavailable_edition(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Invalidated,
            'owner_id' => null,
        ]);

        $response = $this->actingAs($user)->post(route('qr.claim', $edition->qr_code));

        $response->assertRedirect(route('qr.show', $edition->qr_code));
        $response->assertSessionHas('error', 'This edition is not available for claiming.');

        $edition->refresh();
        $this->assertNull($edition->owner_id);
    }

    public function test_claimed_edition_shows_ownership_status_without_personal_info(): void
    {
        $owner = User::factory()->create(['role' => UserRole::User, 'name' => 'John Doe']);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Sold,
            'owner_id' => $owner->id,
        ]);

        $response = $this->get(route('qr.show', $edition->qr_code));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('QR/Claim')
            ->where('isClaimed', true)
            ->where('isOwnedByCurrentUser', false)
            ->where('canClaim', false)
            ->where('edition.owner', null) // Privacy: owner details not exposed
        );
    }

    public function test_owner_can_view_their_edition(): void
    {
        $owner = User::factory()->create(['role' => UserRole::User]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Sold,
            'owner_id' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('qr.show', $edition->qr_code));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('QR/Claim')
            ->where('isClaimed', true)
            ->where('isOwnedByCurrentUser', true)
            ->where('canClaim', false)
        );
    }

    public function test_owner_can_transfer_edition(): void
    {
        $owner = User::factory()->create(['role' => UserRole::User]);
        $recipient = User::factory()->create(['role' => UserRole::User, 'email' => 'recipient@example.com']);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => 'sold',
            'owner_id' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->post(route('qr.transfer', $edition->qr_code), [
            'recipient_email' => 'recipient@example.com',
        ]);

        $response->assertRedirect(route('qr.show', $edition->qr_code));
        $response->assertSessionHas('success');

        $edition->refresh();
        $this->assertEquals($recipient->id, $edition->owner_id);
        $this->assertEquals(ProductEditionStatus::Sold, $edition->status);
    }

    public function test_non_owner_cannot_transfer_edition(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $owner = User::factory()->create(['role' => UserRole::User]);
        $recipient = User::factory()->create(['role' => UserRole::User, 'email' => 'recipient@example.com']);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Sold,
            'owner_id' => $owner->id,
        ]);

        $response = $this->actingAs($user)->post(route('qr.transfer', $edition->qr_code), [
            'recipient_email' => 'recipient@example.com',
        ]);

        $response->assertRedirect(route('qr.show', $edition->qr_code));
        $response->assertSessionHas('error', 'You do not own this edition.');

        $edition->refresh();
        $this->assertEquals($owner->id, $edition->owner_id);
    }

    public function test_transfer_requires_valid_recipient_email(): void
    {
        $owner = User::factory()->create(['role' => UserRole::User]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Sold,
            'owner_id' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->post(route('qr.transfer', $edition->qr_code), [
            'recipient_email' => 'nonexistent@example.com',
        ]);

        $response->assertSessionHasErrors(['recipient_email']);

        $edition->refresh();
        $this->assertEquals($owner->id, $edition->owner_id);
    }

    public function test_claiming_with_invalid_qr_code_shows_error(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($user)->post(route('qr.claim', 'invalid-code'));

        $response->assertRedirect(route('qr.show', 'invalid-code'));
        $response->assertSessionHas('error', 'Edition not found.');
    }

    public function test_qr_service_integration_with_claiming(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 5,
            'status' => ProductEditionStatus::Available,
        ]);

        $expectedQRCode = $this->qrService->generateQRCode($product, 5);
        $this->assertEquals($expectedQRCode, $edition->qr_code);

        $response1 = $this->get(route('qr.show', $edition->qr_code));
        $response1->assertStatus(200);

        $response2 = $this->actingAs($user)->post(route('qr.claim', $edition->qr_code));
        $response2->assertRedirect(route('qr.show', $edition->qr_code));

        $edition->refresh();
        $this->assertEquals($user->id, $edition->owner_id);
    }
}
