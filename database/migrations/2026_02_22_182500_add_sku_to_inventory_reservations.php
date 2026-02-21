<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->foreignId('product_sku_id')->nullable()->after('product_edition_id')->constrained('product_skus')->nullOnDelete();
            $table->index(['product_sku_id', 'status']);
        });

        // Backfill intentionally skipped here for sqlite compatibility in tests.
        // Existing production data can be backfilled with a targeted script once deployed.
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->dropIndex(['product_sku_id', 'status']);
            $table->dropConstrainedForeignId('product_sku_id');
        });
    }
};
