<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create an admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]
        );

        // Create some test users and artists
        $users = User::factory(10)->create();
        $artists = \App\Models\Artist::factory(5)->create();

        // Add some team members
        foreach ($artists as $artist) {
            $teamMembers = $users->random(rand(1, 3));
            $artist->teamMembers()->attach($teamMembers);
        }
    }
}
