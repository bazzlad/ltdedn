<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_connections') || ! Schema::hasColumn('storefront_connections', 'webhook_secret')) {
            return;
        }

        Schema::table('storefront_connections', function (Blueprint $table): void {
            $table->text('webhook_secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('storefront_connections') || ! Schema::hasColumn('storefront_connections', 'webhook_secret')) {
            return;
        }

        Schema::table('storefront_connections', function (Blueprint $table): void {
            $table->text('webhook_secret')->nullable(false)->change();
        });
    }
};
