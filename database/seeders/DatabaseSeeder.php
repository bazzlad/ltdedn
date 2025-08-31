<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret1234'),
            'role' => UserRole::Admin,
        ]);

        // Create artist user
        User::factory()->artist()->create([
            'name' => 'Artist User',
            'email' => 'artist@example.com',
            'password' => bcrypt('secret1234'),
            'role' => UserRole::Artist,
        ]);

        // Create regular user
        User::factory()->user()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret1234'),
            'role' => UserRole::User,
        ]);

        $this->call(ProductSeeder::class);
    }
}
