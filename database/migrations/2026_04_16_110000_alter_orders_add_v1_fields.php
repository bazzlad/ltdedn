<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_amount')->default(0)->after('shipping_amount');
            $table->string('shipping_rate_id')->nullable()->after('shipping_country');
            $table->string('shipping_method_label')->nullable()->after('shipping_rate_id');
            $table->string('shipping_carrier')->nullable()->after('shipping_method_label');
            $table->string('shipping_tracking_number')->nullable()->after('shipping_carrier');
            $table->timestamp('shipped_at')->nullable()->after('shipping_tracking_number');
            $table->unsignedBigInteger('refunded_amount')->default(0)->after('shipped_at');
            $table->timestamp('last_refunded_at')->nullable()->after('refunded_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'tax_amount',
                'shipping_rate_id',
                'shipping_method_label',
                'shipping_carrier',
                'shipping_tracking_number',
                'shipped_at',
                'refunded_amount',
                'last_refunded_at',
            ]);
        });
    }
};
