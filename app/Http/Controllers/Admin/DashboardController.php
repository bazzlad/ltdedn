<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        $stats = match (true) {
            $user->isAdmin() => $this->dashboardService->getAdminStats(),
            $user->isArtist() => $this->dashboardService->getArtistStats($user),
            default => abort(403, 'Unauthorized access to admin dashboard.')
        };

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
