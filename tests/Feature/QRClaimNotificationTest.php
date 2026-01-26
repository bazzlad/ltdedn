<?php

namespace Tests\Feature;

use App\Enums\ProductEditionStatus;
use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use App\Notifications\QRCodeClaimed;
use App\Notifications\QRCodeClaimedConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QRClaimNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_claimer_receives_confirmation_notification_when_claiming_qr(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $claimer = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($claimer)->post(route('qr.claim', $edition->qr_code));

        Notification::assertSentTo(
            $claimer,
            QRCodeClaimedConfirmation::class,
            function ($notification, $channels) use ($edition) {
                return $notification->edition->id === $edition->id
                    && in_array('mail', $channels)
                    && in_array('database', $channels);
            }
        );
    }

    public function test_artist_owner_receives_notification_when_edition_is_claimed(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $claimer = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($claimer)->post(route('qr.claim', $edition->qr_code));

        Notification::assertSentTo(
            $artistOwner,
            QRCodeClaimed::class,
            function ($notification, $channels) use ($edition, $claimer) {
                return $notification->edition->id === $edition->id
                    && $notification->claimer->id === $claimer->id
                    && in_array('mail', $channels)
                    && in_array('database', $channels);
            }
        );
    }

    public function test_both_claimer_and_artist_owner_receive_notifications(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create(['edition_size' => 2]);

        ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 2,
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $claimer = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($claimer)->post(route('qr.claim', $edition->qr_code));

        Notification::assertCount(2);
        Notification::assertSentTo($claimer, QRCodeClaimedConfirmation::class);
        Notification::assertSentTo($artistOwner, QRCodeClaimed::class);
    }

    public function test_notification_is_stored_in_database(): void
    {
        $artistOwner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $claimer = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($claimer)->post(route('qr.claim', $edition->qr_code));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $claimer->id,
            'type' => QRCodeClaimedConfirmation::class,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $artistOwner->id,
            'type' => QRCodeClaimed::class,
        ]);
    }

    public function test_notification_contains_correct_data(): void
    {
        $artistOwner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create(['name' => 'Test Product']);
        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 42,
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $claimer = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => UserRole::User,
        ]);

        $this->actingAs($claimer)->post(route('qr.claim', $edition->qr_code));

        $claimerNotification = $claimer->notifications()->first();
        $this->assertEquals($edition->id, $claimerNotification->data['edition_id']);
        $this->assertEquals($product->id, $claimerNotification->data['product_id']);
        $this->assertEquals('Test Product', $claimerNotification->data['product_name']);
        $this->assertEquals(42, $claimerNotification->data['edition_number']);

        $artistNotification = $artistOwner->notifications()->first();
        $this->assertEquals($edition->id, $artistNotification->data['edition_id']);
        $this->assertEquals($claimer->id, $artistNotification->data['claimer_id']);
        $this->assertEquals('John Doe', $artistNotification->data['claimer_name']);
        $this->assertEquals('john@example.com', $artistNotification->data['claimer_email']);
    }

    public function test_no_notification_sent_if_edition_already_claimed(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create();
        $existingOwner = User::factory()->create(['role' => UserRole::User]);
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Sold,
            'owner_id' => $existingOwner->id,
        ]);

        $claimer = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($claimer)->post(route('qr.claim', $edition->qr_code));

        Notification::assertNothingSent();
    }

    public function test_notifications_are_queued(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $claimer = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($claimer)->post(route('qr.claim', $edition->qr_code));

        Notification::assertSentTo($claimer, function (QRCodeClaimedConfirmation $notification) {
            return $notification instanceof \Illuminate\Contracts\Queue\ShouldQueue;
        });

        Notification::assertSentTo($artistOwner, function (QRCodeClaimed $notification) {
            return $notification instanceof \Illuminate\Contracts\Queue\ShouldQueue;
        });
    }
}
