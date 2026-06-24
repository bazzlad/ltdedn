<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku_code')->unique();
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->string('currency', 3)->default('gbp');
            $table->unsignedInteger('stock_on_hand')->default(0);
            $table->unsignedInteger('stock_reserved')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        Schema::table('product_editions', function (Blueprint $table) {
            $table->foreignId('product_sku_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_skus')
                ->nullOnDelete();

            $table->index(['product_sku_id', 'status']);
        });

        Schema::create('storefront_connections', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('store_url')->nullable();
            $table->text('credentials')->nullable();
            $table->text('webhook_secret');
            $table->string('status', 32)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_meta')->nullable();
            $table->timestamps();

            $table->index(['platform', 'status']);
            $table->unique(['platform', 'name']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('storefront_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_platform', 32)->default('manual')->index();
            $table->string('external_order_id')->nullable();
            $table->string('external_order_number')->nullable();
            $table->string('source_payment_status')->nullable();
            $table->string('source_fulfilment_status')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('currency', 3)->default('gbp');
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->string('customer_email')->nullable();
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_line1')->nullable();
            $table->string('shipping_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('shipping_country', 2)->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->string('shipping_tracking_number')->nullable();
            $table->string('shipment_pushback_status', 32)->nullable();
            $table->text('shipment_pushback_error')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('last_refunded_at')->nullable();
            $table->timestamp('last_pushback_attempted_at')->nullable();
            $table->text('exception_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['source_platform', 'external_order_id']);
            $table->index(['status', 'paid_at']);
            $table->index(['source_platform', 'exception_reason']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_edition_id')->nullable()->constrained('product_editions')->nullOnDelete();
            $table->foreignId('product_sku_id')->nullable()->constrained('product_skus')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_slug')->nullable();
            $table->string('variant_title_snapshot')->nullable();
            $table->string('sku_code_snapshot')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount')->default(0);
            $table->unsignedBigInteger('line_total_amount')->default(0);
            $table->json('attributes_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('product_sku_id');
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 64);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index('type');
        });

        Schema::create('external_order_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 32);
            $table->string('external_order_id')->nullable();
            $table->string('delivery_id')->nullable();
            $table->string('payload_hash', 64);
            $table->text('raw_payload');
            $table->string('status', 32)->default('pending');
            $table->text('error_details')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['storefront_connection_id', 'external_order_id', 'payload_hash'], 'external_import_unique_payload');
            $table->unique(['storefront_connection_id', 'delivery_id'], 'external_import_unique_delivery');
            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_order_imports');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('storefront_connections');

        Schema::table('product_editions', function (Blueprint $table) {
            $table->dropIndex(['product_sku_id', 'status']);
            $table->dropConstrainedForeignId('product_sku_id');
        });

        Schema::dropIfExists('product_skus');
    }
};
