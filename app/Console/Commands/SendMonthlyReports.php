<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\MonthlyReport;
use App\Models\ProductEdition;
use App\Models\User;
use App\Notifications\AdminMonthlyReportNotification;
use App\Notifications\ArtistMonthlyReportNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMonthlyReports extends Command
{
    protected $signature = 'reports:send-monthly {--month= : Month (1-12)} {--year= : Year}';

    protected $description = 'Send monthly reports to artists and admins';

    public function handle(): int
    {
        $month = $this->option('month') ?? now()->subMonth()->month;
        $year = $this->option('year') ?? now()->subMonth()->year;

        $monthName = Carbon::create($year, $month, 1)->format('F');

        $this->info("Generating reports for {$monthName} {$year}...");

        $this->sendArtistReports($month, $year, $monthName);
        $this->sendAdminReports($month, $year, $monthName);

        $this->info('Monthly reports sent successfully!');

        return Command::SUCCESS;
    }

    private function sendArtistReports(int $month, int $year, string $monthName): void
    {
        $artists = Artist::with('owner')->whereHas('owner')->get();
        $previousMonth = $month === 1 ? 12 : $month - 1;
        $previousYear = $month === 1 ? $year - 1 : $year;

        foreach ($artists as $artist) {
            if (! $artist->owner) {
                continue;
            }

            $editionsClaimed = ProductEdition::whereHas('product', function ($query) use ($artist) {
                $query->where('artist_id', $artist->id);
            })
                ->whereNotNull('owner_id')
                ->whereYear('updated_at', $year)
                ->whereMonth('updated_at', $month)
                ->count();

            $totalEditions = ProductEdition::whereHas('product', function ($query) use ($artist) {
                $query->where('artist_id', $artist->id);
            })->count();

            $activeProducts = $artist->products()->count();

            $mostPopularProduct = ProductEdition::whereHas('product', function ($query) use ($artist) {
                $query->where('artist_id', $artist->id);
            })
                ->whereNotNull('owner_id')
                ->whereYear('updated_at', $year)
                ->whereMonth('updated_at', $month)
                ->selectRaw('product_id, COUNT(*) as claims_count')
                ->groupBy('product_id')
                ->orderByDesc('claims_count')
                ->first();

            $mostPopularProductName = $mostPopularProduct
                ? $mostPopularProduct->product->name ?? 'N/A'
                : 'N/A';

            $previousMonthClaimed = ProductEdition::whereHas('product', function ($query) use ($artist) {
                $query->where('artist_id', $artist->id);
            })
                ->whereNotNull('owner_id')
                ->whereYear('updated_at', $previousYear)
                ->whereMonth('updated_at', $previousMonth)
                ->count();

            $reportData = [
                'editions_claimed' => $editionsClaimed,
                'total_editions' => $totalEditions,
                'active_products' => $activeProducts,
                'most_popular_product' => $mostPopularProductName,
                'previous_month_claimed' => $previousMonthClaimed,
            ];

            MonthlyReport::updateOrCreate(
                [
                    'user_id' => $artist->owner->id,
                    'report_type' => 'artist',
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'report_data' => $reportData,
                ]
            );

            $artist->owner->notify(new ArtistMonthlyReportNotification($reportData, $monthName, $year));

            $this->info("Sent report to artist: {$artist->owner->name}");
        }
    }

    private function sendAdminReports(int $month, int $year, string $monthName): void
    {
        $admins = User::where('role', UserRole::Admin)->get();
        $previousMonth = $month === 1 ? 12 : $month - 1;
        $previousYear = $month === 1 ? $year - 1 : $year;

        $totalEditionsClaimed = ProductEdition::whereNotNull('owner_id')
            ->whereYear('updated_at', $year)
            ->whereMonth('updated_at', $month)
            ->count();

        $totalActiveProducts = \App\Models\Product::count();

        $activeArtists = Artist::whereHas('products')->count();

        $newUsers = User::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        $totalUsers = User::count();

        $topArtist = ProductEdition::whereNotNull('owner_id')
            ->whereYear('updated_at', $year)
            ->whereMonth('updated_at', $month)
            ->selectRaw('product_id, COUNT(*) as claims_count')
            ->groupBy('product_id')
            ->orderByDesc('claims_count')
            ->first();

        $topArtistName = 'N/A';
        $topArtistClaims = 0;

        if ($topArtist) {
            $product = \App\Models\Product::find($topArtist->product_id);
            if ($product && $product->artist) {
                $topArtistName = $product->artist->name;
                $topArtistClaims = $topArtist->claims_count;
            }
        }

        $previousMonthClaimed = ProductEdition::whereNotNull('owner_id')
            ->whereYear('updated_at', $previousYear)
            ->whereMonth('updated_at', $previousMonth)
            ->count();

        $reportData = [
            'total_editions_claimed' => $totalEditionsClaimed,
            'total_active_products' => $totalActiveProducts,
            'active_artists' => $activeArtists,
            'new_users' => $newUsers,
            'total_users' => $totalUsers,
            'top_artist' => $topArtistName,
            'top_artist_claims' => $topArtistClaims,
            'previous_month_claimed' => $previousMonthClaimed,
        ];

        foreach ($admins as $admin) {
            MonthlyReport::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'report_type' => 'admin',
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'report_data' => $reportData,
                ]
            );

            $admin->notify(new AdminMonthlyReportNotification($reportData, $monthName, $year));

            $this->info("Sent report to admin: {$admin->name}");
        }
    }
}
