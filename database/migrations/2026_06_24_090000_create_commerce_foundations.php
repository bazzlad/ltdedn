<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createOrUpdateProductSkus();
        $this->createOrUpdateProductEditions();
        $this->createOrUpdateStorefrontConnections();
        $this->createOrUpdateOrders();
        $this->createOrUpdateOrderItems();
        $this->createOrUpdateOrderEvents();
        $this->createOrUpdateExternalOrderImports();
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

    private function createOrUpdateProductSkus(): void
    {
        if (! Schema::hasTable('product_skus')) {
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
        }
    }

    private function createOrUpdateProductEditions(): void
    {
        if (Schema::hasColumn('product_editions', 'product_sku_id')) {
            return;
        }

        Schema::table('product_editions', function (Blueprint $table) {
            $table->foreignId('product_sku_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_skus')
                ->nullOnDelete();

            $table->index(['product_sku_id', 'status']);
        });
    }

    private function createOrUpdateStorefrontConnections(): void
    {
        if (Schema::hasTable('storefront_connections')) {
            return;
        }

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
    }

    private function createOrUpdateOrders(): void
    {
        if (! Schema::hasTable('orders')) {
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

            return;
        }

        $this->addMissingOrderColumns();
    }

    private function addMissingOrderColumns(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'storefront_connection_id')) {
                $table->foreignId('storefront_connection_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'source_platform')) {
                $table->string('source_platform', 32)->default('manual')->after('storefront_connection_id')->index();
            }

            if (! Schema::hasColumn('orders', 'external_order_id')) {
                $table->string('external_order_id')->nullable()->after('source_platform');
            }

            if (! Schema::hasColumn('orders', 'external_order_number')) {
                $table->string('external_order_number')->nullable()->after('external_order_id');
            }

            if (! Schema::hasColumn('orders', 'source_payment_status')) {
                $table->string('source_payment_status')->nullable()->after('external_order_number');
            }

            if (! Schema::hasColumn('orders', 'source_fulfilment_status')) {
                $table->string('source_fulfilment_status')->nullable()->after('source_payment_status');
            }

            if (! Schema::hasColumn('orders', 'shipment_pushback_status')) {
                $table->string('shipment_pushback_status', 32)->nullable()->after('shipping_tracking_number');
            }

            if (! Schema::hasColumn('orders', 'shipment_pushback_error')) {
                $table->text('shipment_pushback_error')->nullable()->after('shipment_pushback_status');
            }

            if (! Schema::hasColumn('orders', 'last_pushback_attempted_at')) {
                $table->timestamp('last_pushback_attempted_at')->nullable()->after('last_refunded_at');
            }

            if (! Schema::hasColumn('orders', 'exception_reason')) {
                $table->text('exception_reason')->nullable()->after('last_pushback_attempted_at');
            }
        });
    }

    private function createOrUpdateOrderItems(): void
    {
        if (! Schema::hasTable('order_items')) {
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

            return;
        }

        if (! Schema::hasColumn('order_items', 'variant_title_snapshot')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('variant_title_snapshot')->nullable()->after('product_slug');
            });
        }
    }

    private function createOrUpdateOrderEvents(): void
    {
        if (Schema::hasTable('order_events')) {
            return;
        }

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
    }

    private function createOrUpdateExternalOrderImports(): void
    {
        if (Schema::hasTable('external_order_imports')) {
            return;
        }

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
};
