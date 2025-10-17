<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductEditionRequest;
use App\Http\Requests\Admin\UpdateProductEditionRequest;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;
// QR code generation
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $editions = $product->editions()
            ->with('owner:id,name')
            ->orderBy('number')
            ->paginate(20);

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
