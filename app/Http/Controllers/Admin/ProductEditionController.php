<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductEditionStatus;
use App\Http\Controllers\Controller;
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
        $this->authorize('view', $product);

        $product->load('artist');

        $perPage = request('per_page', 20); // Default to 20, allow customization
        $perPage = in_array($perPage, [20, 50/* , 100, 200 */]) ? $perPage : 20; // Validate allowed values

        $editions = $product->editions()
            ->with('owner')
            ->orderBy('number')
            ->paginate($perPage);

        return Inertia::render('Admin/Products/Editions/Index', [
            'product' => $product,
            'editions' => $editions,
        ]);
    }

    public function create(Product $product): Response
    {
        $this->authorize('create', [ProductEdition::class, $product]);

        /** @var User $user */
        $user = Auth::user();

        $nextNumber = $product->editions()->max('number') + 1;

        // Get available users for owner selection (admin only)
        $users = $user->isAdmin()
            ? User::select('id', 'name', 'email')->orderBy('name')->get()
            : collect();

        return Inertia::render('Admin/Products/Editions/Create', [
            'product' => $product,
            'nextNumber' => $nextNumber,
            'users' => $users,
            'statuses' => ProductEditionStatus::options(),
        ]);
    }

    public function store(StoreProductEditionRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('create', [ProductEdition::class, $product]);

        $edition = $product->editions()->create($request->validated());

        return redirect()->route('admin.products.editions.index', $product)
            ->with('success', "Edition #{$edition->number} created successfully.");
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

        $product->load('artist');
        $edition->load('owner');

        return Inertia::render('Admin/Products/Editions/Edit', [
            'product' => $product,
            'edition' => $edition,
            'users' => $users,
            'statuses' => ProductEditionStatus::options(),
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
}
