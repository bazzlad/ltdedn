<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_sellable')->default(false)->after('sell_through_ltdedn');
            $table->string('sale_status')->default('draft')->after('is_sellable');
            $table->string('currency', 3)->default('gbp')->after('base_price');
            $table->timestamp('sale_starts_at')->nullable()->after('currency');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_starts_at');

            $table->index(['is_sellable', 'sale_status']);
        });

        Schema::create('product_variant_axes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_axis_id')->constrained('product_variant_axes')->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_variant_axis_id', 'value']);
            $table->index(['product_variant_axis_id', 'sort_order']);
        });

        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku_code')->unique();
            $table->unsignedBigInteger('price_amount');
            $table->unsignedBigInteger('compare_at_amount')->nullable();
            $table->string('currency', 3)->default('gbp');
            $table->unsignedInteger('stock_on_hand')->default(0);
            $table->unsignedInteger('stock_reserved')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_sku_id')->nullable()->after('product_edition_id')->constrained('product_skus')->nullOnDelete();
            $table->string('sku_code_snapshot')->nullable()->after('product_slug');
            $table->json('attributes_snapshot')->nullable()->after('sku_code_snapshot');

            $table->index(['product_sku_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('checkout_expires_at')->nullable()->after('paid_at');
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
            $table->dropColumn('checkout_expires_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['product_sku_id']);
            $table->dropConstrainedForeignId('product_sku_id');
            $table->dropColumn('sku_code_snapshot');
            $table->dropColumn('attributes_snapshot');
        });

        Schema::dropIfExists('product_skus');
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variant_axes');

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_sellable', 'sale_status']);
            $table->dropColumn('is_sellable');
            $table->dropColumn('sale_status');
            $table->dropColumn('currency');
            $table->dropColumn('sale_starts_at');
            $table->dropColumn('sale_ends_at');
        });
    }
};
