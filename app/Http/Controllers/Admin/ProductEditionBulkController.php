<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBulkProductEditionRequest;
use App\Models\Product;
use App\Models\ProductEdition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductEditionBulkController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreBulkProductEditionRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('create', [ProductEdition::class, $product]);

        $validated = $request->validated();
        $startNumber = $validated['start_number'];
        $quantity = $validated['quantity'];
        $status = $validated['status'];
        $ownerId = $validated['owner_id'] ?? null;

        $endNumber = $startNumber + $quantity - 1;

        try {
            DB::transaction(function () use ($product, $startNumber, $endNumber, $status, $ownerId) {
                $existingNumbers = $product->editions()
                    ->whereBetween('number', [$startNumber, $endNumber])
                    ->lockForUpdate()
                    ->pluck('number')
                    ->toArray();

                if (! empty($existingNumbers)) {
                    throw ValidationException::withMessages([
                        'start_number' => 'Edition numbers '.implode(', ', $existingNumbers).' already exist for this product.',
                    ]);
                }

                $editions = collect(range($startNumber, $endNumber))->map(fn (int $number) => [
                    'number' => $number,
                    'status' => $status,
                    'owner_id' => $ownerId,
                ])->all();

                $product->editions()->createMany($editions);
            });

            return redirect()->route('admin.products.editions.index', $product)
                ->with('success', "{$quantity} editions created successfully (#{$startNumber} - #{$endNumber}).");
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('Bulk edition creation failed', [
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
}
