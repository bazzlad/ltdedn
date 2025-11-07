<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBulkProductEditionRequest;
use App\Http\Requests\Admin\StoreProductEditionRequest;
use App\Http\Requests\Admin\UpdateProductEditionRequest;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
// QR code generation
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
// use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevel;
use Inertia\Response;

class ProductEditionController extends Controller
{
    use AuthorizesRequests;

    public function index(Product $product): Response
    {
        $this->authorize('viewAny', ProductEdition::class);
        $this->authorize('view', $product);

        $perPage = request('per_page', 20); // Default to 20, allow customization
        $perPage = in_array($perPage, [20, 50/*, 100, 200*/]) ? $perPage : 20; // Validate allowed values

        $editions = $product->editions()
            ->with('owner:id,name')
            ->orderBy('number')
            ->paginate($perPage);

        // Append the per_page parameter to pagination links
        $editions->appends(request()->query());

        return Inertia::render('Admin/Products/Editions/Index', [
            'product' => $product->load('artist:id,name'),
            'editions' => $editions,
        ]);
    }

    public function create(Product $product): Response
    {
        $this->authorize('create', [ProductEdition::class, $product]);

        /** @var User $user */
        $user = Auth::user();

        // Get next available edition number
        $nextNumber = $product->editions()->max('number') + 1;

        // Get available users for owner selection (admin only)
        $users = $user->isAdmin()
            ? User::select('id', 'name', 'email')->orderBy('name')->get()
            : collect();

        return Inertia::render('Admin/Products/Editions/Create', [
            'product' => $product->load('artist:id,name'),
            'nextNumber' => $nextNumber,
            'users' => $users,
            'statuses' => $this->getStatusOptions(),
        ]);
    }

    public function store(StoreProductEditionRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('create', [ProductEdition::class, $product]);

        try {
            $edition = $product->editions()->create($request->validated());

            return redirect()->route('admin.products.editions.index', $product)
                ->with('success', "Edition #{$edition->number} created successfully.");
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Handle the case where validation didn't catch the duplicate
            return back()->withErrors([
                'number' => 'This edition number already exists for this product.',
            ])->withInput();
        }
    }

    public function storeBulk(StoreBulkProductEditionRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('create', [ProductEdition::class, $product]);

        $validated = $request->validated();
        $startNumber = $validated['start_number'];
        $quantity = $validated['quantity'];
        $status = $validated['status'];
        $ownerId = $validated['owner_id'] ?? null;

        $editions = [];
        for ($i = 0; $i < $quantity; $i++) {
            $editions[] = [
                'product_id' => $product->id,
                'number' => $startNumber + $i,
                'status' => $status,
                'owner_id' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        try {
            // Double-check for existing numbers before creating (in case validation was bypassed)
            $endNumber = $startNumber + $quantity - 1;
            $existingNumbers = ProductEdition::where('product_id', $product->id)
                ->whereBetween('number', [$startNumber, $endNumber])
                ->pluck('number')
                ->toArray();

            if (! empty($existingNumbers)) {
                return back()->withErrors([
                    'start_number' => 'Edition numbers '.implode(', ', $existingNumbers).' already exist for this product.',
                ])->withInput();
            }

            // Create editions one by one to trigger model events (including QR generation)
            $createdCount = 0;
            for ($i = 0; $i < $quantity; $i++) {
                $editionData = [
                    'number' => $startNumber + $i,
                    'status' => $status,
                    'owner_id' => $ownerId,
                ];

                $product->editions()->create($editionData);
                $createdCount++;
            }

            return redirect()->route('admin.products.editions.index', $product)
                ->with('success', "{$createdCount} editions created successfully (#{$startNumber} - #".($startNumber + $createdCount - 1).').');
        } catch (\Exception $e) {
            \Log::error('Bulk edition creation failed', [
                'product_id' => $product->id,
                'start_number' => $startNumber,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'start_number' => 'Failed to create editions. Please try again.',
            ])->withInput();
        }
    }

    public function show(Product $product, ProductEdition $edition): Response
    {
        $this->authorize('view', [$edition, $product]);

        $edition->load('owner:id,name,email');

        return Inertia::render('Admin/Products/Editions/Show', [
            'product' => $product->load('artist:id,name'),
            'edition' => $edition,
        ]);
    }

    public function edit(Product $product, ProductEdition $edition): Response
    {
        $this->authorize('update', [$edition, $product]);

        /** @var User $user */
        $user = Auth::user();

        // Get available users for owner selection (admin only)
        $users = $user->isAdmin()
            ? User::select('id', 'name', 'email')->orderBy('name')->get()
            : collect();

        $edition->load('owner:id,name');

        return Inertia::render('Admin/Products/Editions/Edit', [
            'product' => $product->load('artist:id,name'),
            'edition' => $edition,
            'users' => $users,
            'statuses' => $this->getStatusOptions(),
        ]);
    }

    public function update(UpdateProductEditionRequest $request, Product $product, ProductEdition $edition): RedirectResponse
    {
        $this->authorize('update', [$edition, $product]);

        $edition->update($request->validated());

        return redirect()->route('admin.products.editions.index', $product)
            ->with('success', "Edition #{$edition->number} updated successfully.");
    }

    public function destroy(Product $product, ProductEdition $edition): RedirectResponse
    {
        $this->authorize('delete', [$edition, $product]);

        $editionNumber = $edition->number;
        $edition->delete();

        return redirect()->route('admin.products.editions.index', $product)
            ->with('success', "Edition #{$editionNumber} deleted successfully.");
    }

    private function getStatusOptions(): array
    {
        return [
            ['value' => 'available', 'label' => 'Available'],
            ['value' => 'sold', 'label' => 'Sold'],
            ['value' => 'redeemed', 'label' => 'Redeemed'],
            ['value' => 'pending_transfer', 'label' => 'Pending Transfer'],
            ['value' => 'invalidated', 'label' => 'Invalidated'],
        ];
    }
}
