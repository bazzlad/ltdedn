<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Artist;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', Product::class);

        /** @var User $user */
        $user = Auth::user();

        $query = Product::with([
            'artist:id,name,slug',
            'editions' => function ($query) {
                $query->select('product_id', 'status');
            },
        ])
            ->withCount('editions')
            ->latest();

        // Search functionality
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('artist', function ($artistQuery) use ($search) {
                        $artistQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Role-based data scoping
        if ($user->isArtist()) {
            // Artists can only see products from their owned artists
            $artistIds = $user->ownedArtists()->pluck('id');
            $query->whereIn('artist_id', $artistIds);
        }

        $products = $query->paginate(15);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => request('search'),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        /** @var User $user */
        $user = Auth::user();

        // Get available artists based on role
        $artists = $user->isAdmin()
            ? Artist::select('id', 'name')->orderBy('name')->get()
            : $user->ownedArtists()->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Products/Create', [
            'artists' => $artists,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        // Additional authorization check for specific artist
        /** @var User $user */
        $user = Auth::user();
        if ($user->isArtist()) {
            $this->authorize('manageForArtist', [Product::class, (int) $request->artist_id]);
        }

        Product::create($request->validated());

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function show(Product $product): Response
    {
        $this->authorize('view', $product);

        // Load product with artist data
        $product->load(['artist:id,name,slug']);

        // Get status counts for all editions
        $editionStats = $product->editions()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get total count
        $totalEditions = $product->editions()->count();

        return Inertia::render('Admin/Products/Show', [
            'product' => $product,
            'editionStats' => $editionStats,
            'totalEditions' => $totalEditions,
        ]);
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        /** @var User $user */
        $user = Auth::user();

        // Get available artists based on role
        $artists = $user->isAdmin()
            ? Artist::select('id', 'name')->orderBy('name')->get()
            : $user->ownedArtists()->select('id', 'name')->orderBy('name')->get();

        $product->load('artist:id,name');

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'artists' => $artists,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        // Additional authorization check for new artist if changed
        /** @var User $user */
        $user = Auth::user();
        if ($user->isArtist() && (int) $product->artist_id !== (int) $request->artist_id) {
            $this->authorize('manageForArtist', [Product::class, $request->artist_id]);
        }

        $product->update($request->validated());

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }
}
