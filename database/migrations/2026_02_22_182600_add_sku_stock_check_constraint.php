<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE product_skus ADD CONSTRAINT chk_product_skus_stock_reserved CHECK (stock_reserved <= stock_on_hand)');
        }

        if ($driver === 'sqlite') {
            // SQLite schema-alter constraints are limited; enforce via application layer + tests.
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE product_skus DROP CHECK chk_product_skus_stock_reserved');
        }
    }
};
