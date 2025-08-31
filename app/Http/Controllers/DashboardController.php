<?php

namespace App\Http\Controllers;

use App\Models\ProductEdition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = Auth::user();

        $ownedEditions = ProductEdition::with(['product.artist'])
            ->where('owner_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return Inertia::render('Dashboard', [
            'ownedEditions' => $ownedEditions,
        ]);
    }
}
