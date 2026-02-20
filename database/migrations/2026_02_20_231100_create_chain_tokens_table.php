<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chain_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('product_editions')->cascadeOnDelete();
            $table->string('chain')->default('polygon');
            $table->string('contract_address');
            $table->string('token_id');
            $table->string('mint_tx_hash')->nullable();
            $table->string('last_tx_hash')->nullable();
            $table->timestamp('minted_at')->nullable();
            $table->timestamps();

            $table->unique('edition_id');
            $table->unique(['contract_address', 'token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chain_tokens');
    }
};
