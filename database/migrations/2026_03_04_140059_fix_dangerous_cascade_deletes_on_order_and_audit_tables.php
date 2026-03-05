<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('sku_stock_adjustments', function (Blueprint $table) {
            $table->dropForeign(['product_sku_id']);
            $table->foreign('product_sku_id')->references('id')->on('product_skus')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('sku_stock_adjustments', function (Blueprint $table) {
            $table->dropForeign(['product_sku_id']);
            $table->foreign('product_sku_id')->references('id')->on('product_skus')->cascadeOnDelete();
        });
    }
};
