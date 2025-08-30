<?php

namespace App\Actions;

use App\Models\Artist;
use App\Models\User;

class RemoveTeamMemberAction
{
    public function execute(Artist $artist, User $user): bool
    {
        // Owner cannot be removed
        if ($artist->isOwner($user)) {
            return false;
        }

        // Cannot remove if not a team member
        if (! $artist->hasTeamMember($user)) {
            return false;
        }

        $artist->teamMembers()->detach($user->id);

        return true;
    }
}
