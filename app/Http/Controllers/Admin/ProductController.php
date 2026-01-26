<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexProductRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Artist;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexProductRequest $request): Response
    {
        $this->authorize('viewAny', Product::class);

        /** @var User $user */
        $user = Auth::user();

        $query = Product::with(['artist', 'editions'])->withCount('editions')->latest();

        if ($search = $request->validated('search')) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('artist', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($user->isArtist()) {
            $query->whereIn('artist_id', $user->ownedArtists()->select('id'));
        }

        $products = $query->paginate(15)->through(fn ($product) => new ProductResource($product));

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => $request->validated(),
        ]);
    }

    public function create(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        // Repeating ourself, so should be putting this into a helper
        $artists = $user->isAdmin()
            ? Artist::orderBy('name')->get()
            : $user->ownedArtists()->orderBy('name')->get();

        return Inertia::render('Admin/Products/Create', [
            'artists' => $artists,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', [Product::class, $request->validated('artist_id')]);

        $validated = $request->validated();

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('products', 'public');
        }

        $product = Product::create($validated);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function show(Product $product): Response
    {
        $this->authorize('view', $product);

        $product->load('artist')->loadCount('editions');

        $editionStats = $product->editions()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return Inertia::render('Admin/Products/Show', [
            'product' => new ProductResource($product),
            'editionStats' => $editionStats,
        ]);
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        /** @var User $user */
        $user = Auth::user();

        // Repeating ourself, so should be putting this into a helper
        $artists = $user->isAdmin()
            ? Artist::orderBy('name')->get()
            : $user->ownedArtists()->orderBy('name')->get();

        $product->load('artist');

        return Inertia::render('Admin/Products/Edit', [
            'product' => new ProductResource($product),
            'artists' => $artists,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();

        if ($request->hasFile('cover_image')) {
            if ($product->cover_image && Storage::disk('public')->exists($product->cover_image)) {
                Storage::disk('public')->delete($product->cover_image);
            }

            $validated['cover_image'] = $request->file('cover_image')->store('products', 'public');
        }

        $product->update($validated);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        if ($product->cover_image && Storage::disk('public')->exists($product->cover_image)) {
            Storage::disk('public')->delete($product->cover_image);
        }

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }
}
