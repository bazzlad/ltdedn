<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_editions', function (Blueprint $table) {
            $table->dropForeign(['product_sku_id']);
            $table->foreign('product_sku_id')->references('id')->on('product_skus')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_editions', function (Blueprint $table) {
            $table->dropForeign(['product_sku_id']);
            $table->foreign('product_sku_id')->references('id')->on('product_skus')->nullOnDelete();
        });
    }
};
