<?php

namespace Tests\Feature;

use App\Enums\ProductEditionStatus;
use App\Models\Artist;
use App\Models\MonthlyReport;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use App\Notifications\AdminMonthlyReportNotification;
use App\Notifications\ArtistMonthlyReportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_monthly_report_command_sends_notifications_to_artists(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->artist()->create();
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create();

        ProductEdition::factory()->for($product)->create([
            'owner_id' => User::factory()->create()->id,
            'status' => ProductEditionStatus::Sold,
            'updated_at' => now()->subMonth(),
        ]);

        $lastMonth = now()->subMonth()->month;
        $lastYear = now()->subMonth()->year;

        Artisan::call('reports:send-monthly', [
            '--month' => $lastMonth,
            '--year' => $lastYear,
        ]);

        Notification::assertSentTo($artistOwner, ArtistMonthlyReportNotification::class);
    }

    public function test_monthly_report_command_sends_notifications_to_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $lastMonth = now()->subMonth()->month;
        $lastYear = now()->subMonth()->year;

        Artisan::call('reports:send-monthly', [
            '--month' => $lastMonth,
            '--year' => $lastYear,
        ]);

        Notification::assertSentTo($admin, AdminMonthlyReportNotification::class);
    }

    public function test_monthly_report_stores_data_in_database(): void
    {
        $artistOwner = User::factory()->artist()->create();
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create();

        ProductEdition::factory()->for($product)->create([
            'owner_id' => User::factory()->create()->id,
            'status' => ProductEditionStatus::Sold,
            'updated_at' => now()->subMonth(),
        ]);

        $lastMonth = now()->subMonth()->month;
        $lastYear = now()->subMonth()->year;

        Artisan::call('reports:send-monthly', [
            '--month' => $lastMonth,
            '--year' => $lastYear,
        ]);

        $this->assertDatabaseHas('monthly_reports', [
            'user_id' => $artistOwner->id,
            'report_type' => 'artist',
            'year' => $lastYear,
            'month' => $lastMonth,
        ]);
    }

    public function test_artist_report_includes_correct_data(): void
    {
        Notification::fake();

        $artistOwner = User::factory()->artist()->create();
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create(['name' => 'Test Product']);

        ProductEdition::factory()->for($product)->count(3)->create([
            'owner_id' => User::factory()->create()->id,
            'status' => ProductEditionStatus::Sold,
            'updated_at' => now()->subMonth(),
        ]);

        $lastMonth = now()->subMonth()->month;
        $lastYear = now()->subMonth()->year;

        Artisan::call('reports:send-monthly', [
            '--month' => $lastMonth,
            '--year' => $lastYear,
        ]);

        Notification::assertSentTo(
            $artistOwner,
            ArtistMonthlyReportNotification::class,
            function ($notification) {
                return $notification->reportData['editions_claimed'] === 3;
            }
        );
    }

    public function test_admin_report_includes_platform_wide_data(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $artistOwner = User::factory()->artist()->create();
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);
        $product = Product::factory()->for($artist)->create();

        ProductEdition::factory()->for($product)->count(5)->create([
            'owner_id' => User::factory()->create()->id,
            'status' => ProductEditionStatus::Sold,
            'updated_at' => now()->subMonth(),
        ]);

        User::factory()->count(3)->create([
            'created_at' => now()->subMonth(),
        ]);

        $lastMonth = now()->subMonth()->month;
        $lastYear = now()->subMonth()->year;

        Artisan::call('reports:send-monthly', [
            '--month' => $lastMonth,
            '--year' => $lastYear,
        ]);

        Notification::assertSentTo(
            $admin,
            AdminMonthlyReportNotification::class,
            function ($notification) {
                return $notification->reportData['total_editions_claimed'] === 5
                    && $notification->reportData['new_users'] === 3;
            }
        );
    }

    public function test_report_not_sent_if_artist_has_no_owner(): void
    {
        Notification::fake();

        Artist::factory()->create(['owner_id' => null]);

        $lastMonth = now()->subMonth()->month;
        $lastYear = now()->subMonth()->year;

        Artisan::call('reports:send-monthly', [
            '--month' => $lastMonth,
            '--year' => $lastYear,
        ]);

        Notification::assertNothingSent();
    }

    public function test_monthly_report_can_be_retrieved_from_database(): void
    {
        $artistOwner = User::factory()->artist()->create();
        $artist = Artist::factory()->create(['owner_id' => $artistOwner->id]);

        $lastMonth = now()->subMonth()->month;
        $lastYear = now()->subMonth()->year;

        Artisan::call('reports:send-monthly', [
            '--month' => $lastMonth,
            '--year' => $lastYear,
        ]);

        $report = MonthlyReport::where('user_id', $artistOwner->id)
            ->where('report_type', 'artist')
            ->where('year', $lastYear)
            ->where('month', $lastMonth)
            ->first();

        $this->assertNotNull($report);
        $this->assertIsArray($report->report_data);
        $this->assertArrayHasKey('editions_claimed', $report->report_data);
    }
}
