<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chain')->default('polygon');
            $table->string('address')->unique();
            $table->longText('encrypted_private_key');
            $table->unsignedSmallInteger('encryption_version')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'chain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
