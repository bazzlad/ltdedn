<?php

namespace App\Actions;

use App\Models\Artist;
use App\Models\User;

class AddTeamMemberAction
{
    public function execute(Artist $artist, User $user): bool
    {
        // Cannot add owner as team member
        if ($artist->isOwner($user)) {
            return false;
        }

        // Cannot add if already a team member
        if ($artist->hasTeamMember($user)) {
            return false;
        }

        $artist->teamMembers()->attach($user->id);

        return true;
    }
}
