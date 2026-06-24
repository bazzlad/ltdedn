<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('storefront_connections', function (Blueprint $table): void {
            $table->string('external_shop_id')->nullable()->after('store_url');
            $table->string('external_shop_domain')->nullable()->after('external_shop_id');
            $table->json('oauth_scopes')->nullable()->after('credentials');
            $table->timestamp('token_expires_at')->nullable()->after('oauth_scopes');
            $table->text('refresh_token')->nullable()->after('token_expires_at');
            $table->string('webhook_subscription_id')->nullable()->after('webhook_secret');
            $table->string('connection_status', 32)->default('needs_setup')->after('status');
            $table->text('last_connection_error')->nullable()->after('connection_status');
            $table->timestamp('tested_at')->nullable()->after('last_connection_error');
            $table->timestamp('activated_at')->nullable()->after('tested_at');

            $table->unique(['platform', 'external_shop_domain'], 'storefront_connections_platform_shop_domain_unique');
            $table->unique(['platform', 'external_shop_id'], 'storefront_connections_platform_shop_id_unique');
            $table->index(['connection_status', 'tested_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storefront_connections', function (Blueprint $table): void {
            $table->dropUnique('storefront_connections_platform_shop_domain_unique');
            $table->dropUnique('storefront_connections_platform_shop_id_unique');
            $table->dropIndex(['connection_status', 'tested_at']);
            $table->dropColumn([
                'external_shop_id',
                'external_shop_domain',
                'oauth_scopes',
                'token_expires_at',
                'refresh_token',
                'webhook_subscription_id',
                'connection_status',
                'last_connection_error',
                'tested_at',
                'activated_at',
            ]);
        });
    }
};
