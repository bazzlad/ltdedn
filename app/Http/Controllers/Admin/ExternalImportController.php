<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalOrderImport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExternalImportController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', \App\Models\Order::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,processed,ignored,exception,failed'],
            'platform' => ['nullable', 'in:shopify,squarespace,pipe17'],
        ]);

        $imports = ExternalOrderImport::query()
            ->with(['connection.artist:id,name', 'order:id,status,exception_reason'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['platform'] ?? null, fn ($query, string $platform) => $query->where('platform', $platform))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ExternalOrderImport $import) => [
                'id' => $import->id,
                'platform' => $import->platform->value,
                'external_order_id' => $import->external_order_id,
                'delivery_id' => $import->delivery_id,
                'status' => $import->status->value,
                'error_details' => $import->error_details,
                'processed_at' => $import->processed_at ? (string) $import->processed_at : null,
                'created_at' => (string) $import->created_at,
                'order_id' => $import->order_id,
                'order_status' => $import->order?->status?->value,
                'artist_name' => $import->connection?->artist?->name,
            ]);

        return Inertia::render('Admin/ExternalImports/Index', [
            'imports' => $imports,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'platform' => $filters['platform'] ?? '',
            ],
        ]);
    }
}
