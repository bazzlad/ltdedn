<?php

namespace Tests\Feature;

use App\Enums\ProductEditionStatus;
use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use App\Notifications\AccountCreatedByAdminNotification;
use App\Notifications\EditionsSoldOutNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_welcome_notification_sent_on_self_registration(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_account_created_notification_sent_when_admin_creates_user(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::User->value,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo(
            $user,
            AccountCreatedByAdminNotification::class,
            function ($notification) use ($user) {
                return $notification->role === $user->role
                    && ! empty($notification->plainPassword);
            }
        );
    }

    public function test_sold_out_notification_sent_when_last_edition_claimed(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->artist()->create();
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create(['edition_size' => 2]);

        $edition1 = ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'status' => ProductEditionStatus::Sold,
            'owner_id' => User::factory()->create()->id,
        ]);

        $edition2 = ProductEdition::factory()->for($product)->create([
            'number' => 2,
            'status' => ProductEditionStatus::Available,
            'owner_id' => null,
        ]);

        $claimer = User::factory()->create();

        $response = $this->actingAs($claimer)->post(route('qr.claim', $edition2->qr_code));

        $response->assertRedirect(route('qr.show', $edition2->qr_code));

        Notification::assertSentTo(
            $artistOwner,
            EditionsSoldOutNotification::class,
            function ($notification) use ($product) {
                return $notification->product->id === $product->id;
            }
        );
    }

    public function test_no_sold_out_notification_if_editions_still_available(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->artist()->create();
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create(['edition_size' => 3]);

        ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $edition2 = ProductEdition::factory()->for($product)->create([
            'number' => 2,
            'status' => ProductEditionStatus::Available,
        ]);

        ProductEdition::factory()->for($product)->create([
            'number' => 3,
            'status' => ProductEditionStatus::Available,
        ]);

        $claimer = User::factory()->create();

        $response = $this->actingAs($claimer)->post(route('qr.claim', $edition2->qr_code));

        $response->assertRedirect(route('qr.show', $edition2->qr_code));

        Notification::assertNotSentTo($artistOwner, EditionsSoldOutNotification::class);
    }

    public function test_notifications_are_stored_in_database(): void
    {
        $user = User::factory()->create();

        $user->notify(new WelcomeNotification);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => WelcomeNotification::class,
        ]);
    }

    public function test_notifications_are_queued(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->notify(new WelcomeNotification);

        Notification::assertSentTo($user, function (WelcomeNotification $notification) {
            return $notification instanceof \Illuminate\Contracts\Queue\ShouldQueue;
        });
    }
}
