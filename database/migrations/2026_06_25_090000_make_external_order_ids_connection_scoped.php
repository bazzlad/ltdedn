<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasIndex('orders', ['source_platform', 'external_order_id'], 'unique')) {
                $table->dropUnique(['source_platform', 'external_order_id']);
            }

            if (! Schema::hasIndex('orders', ['storefront_connection_id', 'source_platform', 'external_order_id'], 'unique')) {
                $table->unique(['storefront_connection_id', 'source_platform', 'external_order_id'], 'orders_connection_source_external_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasIndex('orders', ['storefront_connection_id', 'source_platform', 'external_order_id'], 'unique')) {
                $table->dropUnique('orders_connection_source_external_unique');
            }

            if (! Schema::hasIndex('orders', ['source_platform', 'external_order_id'], 'unique')) {
                $table->unique(['source_platform', 'external_order_id']);
            }
        });
    }
};
