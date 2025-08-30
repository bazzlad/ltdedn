<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArtistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing artist users from UserSeeder
        $artistUsers = User::where('role', UserRole::Artist)->get();

        // Create artists owned by artist users
        if ($artistUsers->count() >= 1) {
            $artist1 = Artist::factory()->create([
                'name' => 'Digital Dreams Studio',
                'owner_id' => $artistUsers[0]->id,
            ]);
        }

        if ($artistUsers->count() >= 2) {
            $artist2 = Artist::factory()->create([
                'name' => 'Neon Collective',
                'owner_id' => $artistUsers[1]->id,
            ]);
        }

        // Create additional team members
        $teamMember1 = User::factory()->create([
            'name' => 'Sarah Manager',
            'email' => 'sarah.manager@example.com',
            'role' => UserRole::User,
        ]);

        $teamMember2 = User::factory()->create([
            'name' => 'Mike Assistant',
            'email' => 'mike.assistant@example.com',
            'role' => UserRole::User,
        ]);

        $teamMember3 = User::factory()->create([
            'name' => 'Lisa Coordinator',
            'email' => 'lisa.coordinator@example.com',
            'role' => UserRole::User,
        ]);

        // Add team members to artists
        if (isset($artist1)) {
            $artist1->addTeamMember($teamMember1);
            $artist1->addTeamMember($teamMember2);
        }

        if (isset($artist2)) {
            $artist2->addTeamMember($teamMember3);
        }

        // Create a solo artist (owner only)
        if ($artistUsers->count() >= 3) {
            Artist::factory()->create([
                'name' => 'Pixel Pioneers',
                'owner_id' => $artistUsers[2]->id,
            ]);
        }
    }
}
