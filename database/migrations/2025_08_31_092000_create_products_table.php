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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();

            $table->string('qr_secret'); // random per product, rotate if needed
            $table->boolean('sell_through_ltdedn')->default(false); // else sell yourself

            $table->boolean('is_limited')->default(true);
            $table->unsignedInteger('edition_size')->nullable(); // null for open edition, suppressed in UI

            $table->json('variants_schema')->nullable(); // size, color, paper stock
            $table->json('physical')->nullable(); // dims, weight, packaging
            $table->decimal('base_price', 12, 2)->nullable(); // future
            $table->boolean('is_public')->default(false); // SEO later
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
