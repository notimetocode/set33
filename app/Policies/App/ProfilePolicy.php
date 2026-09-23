<?php

namespace App\Policies\App;

use App\Models\User;

class ProfilePolicy
{
    /**
     * App (user area) users may view their own profile.
     */
    public function view(User $actor, User $profile): bool
    {
        return $actor->is($profile);
    }

    /**
     * App (user area) users may update their own profile.
     */
    public function update(User $actor, User $profile): bool
    {
        return $actor->is($profile);
    }
}
