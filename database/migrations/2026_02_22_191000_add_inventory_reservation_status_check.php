<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT chk_inventory_reservations_status CHECK (status IN ('active','consumed','expired','released'))");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE inventory_reservations DROP CHECK chk_inventory_reservations_status');
        }
    }
};
