<?php

namespace Tests\Feature;

use App\Enums\ProductEditionStatus;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductEditionTransfer;
use App\Models\User;
use App\Notifications\ProductEditionTransferAccepted;
use App\Notifications\ProductEditionTransferCancelled;
use App\Notifications\ProductEditionTransferExpired;
use App\Notifications\ProductEditionTransferRejected;
use App\Notifications\QRCodeTransferred;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TransferAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $recipient;

    protected ProductEdition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->recipient = User::factory()->create();
        $artist = \App\Models\Artist::factory()->create(['owner_id' => $this->owner->id]);
        $product = Product::factory()->create(['artist_id' => $artist->id]);
        $this->edition = ProductEdition::factory()->create([
            'product_id' => $product->id,
            'owner_id' => $this->owner->id,
            'status' => ProductEditionStatus::Sold,
            'qr_code' => 'test-qr-code',
        ]);
    }

    public function test_transfer_creates_pending_transfer_record()
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->post(route('qr.transfer', $this->edition->qr_code), [
                'recipient_email' => $this->recipient->email,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_edition_transfers', [
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'product_edition_id' => $this->edition->id,
            'status' => 'pending',
        ]);

        $this->assertEquals(ProductEditionStatus::PendingTransfer, $this->edition->fresh()->status);

        Notification::assertSentTo($this->recipient, QRCodeTransferred::class);
    }

    public function test_recipient_can_accept_transfer()
    {
        Notification::fake();

        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->addHours(48),
            'status' => 'pending',
        ]);

        $this->edition->update(['status' => ProductEditionStatus::PendingTransfer]);

        $this->actingAs($this->recipient)
            ->post(route('transfers.accept.post', $transfer->token))
            ->assertRedirect(route('qr.show', $this->edition->qr_code));

        $this->assertEquals(ProductEditionStatus::Sold, $this->edition->fresh()->status);
        $this->assertEquals($this->recipient->id, $this->edition->fresh()->owner_id);
        $this->assertEquals('accepted', $transfer->fresh()->status);

        Notification::assertSentTo($this->owner, ProductEditionTransferAccepted::class);
    }

    public function test_recipient_can_reject_transfer()
    {
        Notification::fake();

        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->addHours(48),
            'status' => 'pending',
        ]);

        $this->edition->update(['status' => ProductEditionStatus::PendingTransfer]);

        $this->actingAs($this->recipient)
            ->post(route('transfers.reject', $transfer->token))
            ->assertRedirect(route('dashboard'));

        $this->assertEquals(ProductEditionStatus::Sold, $this->edition->fresh()->status);
        $this->assertEquals($this->owner->id, $this->edition->fresh()->owner_id);
        $this->assertEquals('rejected', $transfer->fresh()->status);

        Notification::assertSentTo($this->owner, ProductEditionTransferRejected::class);
    }

    public function test_sender_can_cancel_pending_transfer()
    {
        Notification::fake();

        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->addHours(48),
            'status' => 'pending',
        ]);

        $this->edition->update(['status' => ProductEditionStatus::PendingTransfer]);

        $this->actingAs($this->owner)
            ->post(route('transfers.cancel', $transfer->token))
            ->assertRedirect(route('qr.show', $this->edition->qr_code));

        $this->assertEquals(ProductEditionStatus::Sold, $this->edition->fresh()->status);
        $this->assertEquals($this->owner->id, $this->edition->fresh()->owner_id);
        $this->assertEquals('cancelled', $transfer->fresh()->status);

        Notification::assertSentTo($this->recipient, ProductEditionTransferCancelled::class);
    }

    public function test_non_recipient_cannot_accept_transfer()
    {
        $otherUser = User::factory()->create();

        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->addHours(48),
            'status' => 'pending',
        ]);

        $this->actingAs($otherUser)
            ->post(route('transfers.accept.post', $transfer->token))
            ->assertForbidden();

        $this->assertEquals('pending', $transfer->fresh()->status);
    }

    public function test_expired_transfer_cannot_be_accepted()
    {
        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->subHour(),
            'status' => 'pending',
        ]);

        $this->actingAs($this->recipient)
            ->post(route('transfers.accept.post', $transfer->token))
            ->assertForbidden();
    }

    public function test_transfer_expires_after_48_hours()
    {
        $this->actingAs($this->owner)
            ->post(route('qr.transfer', $this->edition->qr_code), [
                'recipient_email' => $this->recipient->email,
            ]);

        $transfer = ProductEditionTransfer::first();
        $this->assertTrue($transfer->expires_at->greaterThan(now()->addHours(47)));
        $this->assertTrue($transfer->expires_at->lessThanOrEqualTo(now()->addHours(48)));
    }

    public function test_expired_transfers_return_to_sender()
    {
        Notification::fake();

        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->subHour(),
            'status' => 'pending',
        ]);

        $this->edition->update(['status' => ProductEditionStatus::PendingTransfer]);

        $this->artisan('transfers:expire');

        $this->assertEquals(ProductEditionStatus::Sold, $this->edition->fresh()->status);
        $this->assertEquals($this->owner->id, $this->edition->fresh()->owner_id);
        $this->assertEquals('expired', $transfer->fresh()->status);

        Notification::assertSentTo($this->owner, ProductEditionTransferExpired::class);
        Notification::assertSentTo($this->recipient, ProductEditionTransferExpired::class);
    }

    public function test_sender_cannot_cancel_accepted_transfer()
    {
        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->addHours(48),
            'status' => 'accepted',
        ]);

        $this->actingAs($this->owner)
            ->post(route('transfers.cancel', $transfer->token))
            ->assertRedirect()
            ->assertSessionHas('error', 'Transfer is not pending.');
    }

    public function test_recipient_cannot_accept_cancelled_transfer()
    {
        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->addHours(48),
            'status' => 'cancelled',
        ]);

        $this->edition->update(['status' => ProductEditionStatus::Sold]);

        $response = $this->actingAs($this->recipient)
            ->from(route('transfers.accept', $transfer->token))
            ->post(route('transfers.accept.post', $transfer->token));

        $response->assertRedirect()
            ->assertSessionHas('error', 'Transfer is no longer pending.');
    }

    public function test_accept_page_shows_correct_info()
    {
        $transfer = ProductEditionTransfer::create([
            'product_edition_id' => $this->edition->id,
            'sender_id' => $this->owner->id,
            'recipient_id' => $this->recipient->id,
            'expires_at' => now()->addHours(48),
            'status' => 'pending',
        ]);

        $this->actingAs($this->recipient)
            ->get(route('transfers.accept', $transfer->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Transfers/Accept')
                ->has('transfer')
                ->where('transfer.id', $transfer->id)
                ->where('transfer.token', $transfer->token)
                ->where('transfer.status', 'pending')
            );
    }
}
