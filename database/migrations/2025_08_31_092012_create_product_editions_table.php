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
        Schema::create('product_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->unique(['product_id', 'number']);
            $table->enum('status', ['available', 'sold', 'redeemed', 'pending_transfer', 'invalidated'])->default('available')->index();
            $table->string('qr_code')->unique(); // long unique hash string used in URL
            $table->string('qr_short_code')->nullable()->unique(); // optional short code
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_editions');
    }
};
