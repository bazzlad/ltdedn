<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sku_variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sku_id')->constrained('product_skus')->cascadeOnDelete();
            $table->foreignId('product_variant_axis_id')->constrained('product_variant_axes')->restrictOnDelete();
            $table->foreignId('product_variant_value_id')->constrained('product_variant_values')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['product_sku_id', 'product_variant_axis_id'], 'sku_axis_unique');
            $table->unique(['product_sku_id', 'product_variant_value_id'], 'sku_value_unique');
            $table->index('product_variant_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sku_variant_values');
    }
};
