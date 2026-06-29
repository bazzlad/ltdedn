<?php

namespace Tests\Feature\Admin;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_migration_creates_skus_for_more_than_one_chunk(): void
    {
        $artist = Artist::factory()->create();

        Product::factory()
            ->for($artist)
            ->count(125)
            ->sequence(fn ($sequence) => [
                'name' => 'Backfill Product '.$sequence->index,
                'slug' => 'backfill-product-'.$sequence->index,
            ])
            ->create();

        $migration = include database_path('migrations/2026_06_29_112000_backfill_default_product_skus.php');
        $migration->up();

        $this->assertSame(125, ProductSku::query()->count());
        $this->assertDatabaseHas('product_skus', ['sku_code' => 'LTD-1-BACKFILL-PRODUCT-0']);
        $this->assertDatabaseHas('product_skus', ['sku_code' => 'LTD-125-BACKFILL-PRODUCT-124']);
    }
}
