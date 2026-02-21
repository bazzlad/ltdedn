<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_editions', function (Blueprint $table) {
            $table->foreignId('product_sku_id')->nullable()->after('product_id')->constrained('product_skus')->nullOnDelete();
            $table->index(['product_id', 'product_sku_id', 'status'], 'product_editions_product_sku_status_idx');
        });

        $driver = DB::getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement(
                'UPDATE product_editions pe
                LEFT JOIN (
                    SELECT product_id, MIN(id) AS min_id FROM product_skus GROUP BY product_id
                ) seed ON seed.product_id = pe.product_id
                SET pe.product_sku_id = COALESCE(pe.product_sku_id, seed.min_id)
                WHERE pe.product_sku_id IS NULL'
            );
        } else {
            DB::statement(
                'UPDATE product_editions
                SET product_sku_id = (
                    SELECT MIN(ps.id)
                    FROM product_skus ps
                    WHERE ps.product_id = product_editions.product_id
                )
                WHERE product_sku_id IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('product_editions', function (Blueprint $table) {
            $table->dropIndex('product_editions_product_sku_status_idx');
            $table->dropConstrainedForeignId('product_sku_id');
        });
    }
};
