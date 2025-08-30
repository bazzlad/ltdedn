<?php

namespace Database\Seeders;

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
        ]);

        // Create artist user
        User::factory()->artist()->create([
            'name' => 'Artist User',
            'email' => 'artist@example.com',
        ]);

        // Create regular user
        User::factory()->user()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
        ]);

        // Create additional test users
        User::factory(5)->user()->create();
        User::factory(2)->artist()->create();

        // Seed artists and their teams
        $this->call(ArtistSeeder::class);
    }
}
