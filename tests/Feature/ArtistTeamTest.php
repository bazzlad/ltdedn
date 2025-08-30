<?php

namespace Tests\Feature;

use App\Actions\AddTeamMemberAction;
use App\Actions\RemoveTeamMemberAction;
use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_has_owner_and_can_have_team_members(): void
    {
        $owner = User::factory()->create();
        $teamMember1 = User::factory()->create();
        $teamMember2 = User::factory()->create();

        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $addAction = new AddTeamMemberAction;
        $this->assertTrue($addAction->execute($artist, $teamMember1));
        $this->assertTrue($addAction->execute($artist, $teamMember2));

        $this->assertTrue($artist->isOwner($owner));
        $this->assertTrue($artist->hasTeamMember($teamMember1));
        $this->assertTrue($artist->hasTeamMember($teamMember2));
        $this->assertCount(2, $artist->teamMembers);
    }

    public function test_owner_cannot_be_added_as_team_member(): void
    {
        $owner = User::factory()->create();
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        // Action should return false when trying to add owner as team member
        $addAction = new AddTeamMemberAction;
        $this->assertFalse($addAction->execute($artist, $owner));

        $this->assertFalse($artist->hasTeamMember($owner));
        $this->assertCount(0, $artist->teamMembers);
    }

    public function test_owner_and_team_members_can_manage_artist(): void
    {
        $owner = User::factory()->create();
        $teamMember = User::factory()->create();
        $outsider = User::factory()->create();

        $artist = Artist::factory()->create(['owner_id' => $owner->id]);
        $addAction = new AddTeamMemberAction;
        $this->assertTrue($addAction->execute($artist, $teamMember));

        $this->assertTrue($owner->can('update', $artist));
        $this->assertTrue($teamMember->can('update', $artist));
        $this->assertFalse($outsider->can('update', $artist));
    }

    public function test_only_owner_can_be_removed_from_team(): void
    {
        $owner = User::factory()->create();
        $teamMember = User::factory()->create();

        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        // Add team member successfully
        $addAction = new AddTeamMemberAction;
        $this->assertTrue($addAction->execute($artist, $teamMember));
        $this->assertTrue($artist->hasTeamMember($teamMember));

        // Owner cannot be removed (action returns false)
        $removeAction = new RemoveTeamMemberAction;
        $this->assertFalse($removeAction->execute($artist, $owner));
        $this->assertTrue($artist->isOwner($owner));

        // Team member can be removed (action returns true)
        $this->assertTrue($removeAction->execute($artist, $teamMember));
        $this->assertFalse($artist->hasTeamMember($teamMember));
    }

    public function test_admin_can_manage_any_artist(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($admin->can('update', $artist));
    }

    public function test_user_with_artist_role_can_create_artist(): void
    {
        $artistUser = User::factory()->artist()->create();
        $regularUser = User::factory()->user()->create();

        $this->assertTrue($artistUser->can('create', Artist::class));
        $this->assertFalse($regularUser->can('create', Artist::class));
    }

    public function test_user_can_own_multiple_artists(): void
    {
        $owner = User::factory()->create();
        $artist1 = Artist::factory()->create(['owner_id' => $owner->id]);
        $artist2 = Artist::factory()->create(['owner_id' => $owner->id]);

        $this->assertCount(2, $owner->ownedArtists);
        $this->assertTrue($owner->can('update', $artist1));
        $this->assertTrue($owner->can('update', $artist2));
    }

    public function test_user_can_be_part_of_multiple_artist_teams(): void
    {
        $user = User::factory()->create();
        $owner1 = User::factory()->create();
        $owner2 = User::factory()->create();

        $artist1 = Artist::factory()->create(['owner_id' => $owner1->id]);
        $artist2 = Artist::factory()->create(['owner_id' => $owner2->id]);

        $addAction = new AddTeamMemberAction;
        $this->assertTrue($addAction->execute($artist1, $user));
        $this->assertTrue($addAction->execute($artist2, $user));

        $this->assertCount(2, $user->artistTeams);
        $this->assertTrue($user->can('update', $artist1));
        $this->assertTrue($user->can('update', $artist2));
    }

    public function test_user_can_manage_artist_if_part_of_team(): void
    {
        $regularUser = User::factory()->user()->create();
        $owner = User::factory()->create();

        $artist = Artist::factory()->create(['owner_id' => $owner->id]);

        // Initially, regular user cannot manage artist
        $this->assertFalse($regularUser->can('update', $artist));

        // After joining artist team, they can manage the artist
        $addAction = new AddTeamMemberAction;
        $this->assertTrue($addAction->execute($artist, $regularUser));

        $this->assertTrue($regularUser->can('update', $artist));
    }

    public function test_artist_slug_is_automatically_generated(): void
    {
        $owner = User::factory()->create();
        $artist = Artist::factory()->create([
            'name' => 'Test Artist Name',
            'owner_id' => $owner->id,
        ]);

        $this->assertEquals('test-artist-name', $artist->slug);
    }

    public function test_artist_policies_work_correctly(): void
    {
        $admin = User::factory()->admin()->create();
        $artistUser = User::factory()->artist()->create();
        $regularUser = User::factory()->user()->create();
        $owner = User::factory()->create();
        $teamMember = User::factory()->create();

        $artist = Artist::factory()->create(['owner_id' => $owner->id]);
        $addAction = new AddTeamMemberAction;
        $this->assertTrue($addAction->execute($artist, $teamMember));

        // View permissions
        $this->assertTrue($admin->can('view', $artist));
        $this->assertTrue($owner->can('view', $artist));
        $this->assertTrue($teamMember->can('view', $artist));
        $this->assertFalse($regularUser->can('view', $artist));

        // Create permissions
        $this->assertTrue($admin->can('create', Artist::class));
        $this->assertTrue($artistUser->can('create', Artist::class));
        $this->assertFalse($regularUser->can('create', Artist::class));

        // Update permissions
        $this->assertTrue($admin->can('update', $artist));
        $this->assertTrue($owner->can('update', $artist));
        $this->assertTrue($teamMember->can('update', $artist));
        $this->assertFalse($regularUser->can('update', $artist));

        // Delete permissions (only owner and admin)
        $this->assertTrue($admin->can('delete', $artist));
        $this->assertTrue($owner->can('delete', $artist));
        $this->assertFalse($teamMember->can('delete', $artist));
        $this->assertFalse($regularUser->can('delete', $artist));

        // Team management permissions (only owner and admin)
        $this->assertTrue($admin->can('manageTeam', $artist));
        $this->assertTrue($owner->can('manageTeam', $artist));
        $this->assertFalse($teamMember->can('manageTeam', $artist));
        $this->assertFalse($regularUser->can('manageTeam', $artist));
    }
}
