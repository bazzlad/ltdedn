<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sku_id')->constrained('product_skus')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('delta_on_hand')->default(0);
            $table->unsignedInteger('before_on_hand');
            $table->unsignedInteger('after_on_hand');
            $table->string('reason', 100);
            $table->string('source', 50);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_sku_id', 'created_at']);
            $table->index(['source', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_stock_adjustments');
    }
};
