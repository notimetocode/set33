<?php

namespace App\Policies\App;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Site $site): bool
    {
        return $actor->is($site->user);
    }

    public function create(User $actor): bool
    {
        return true;
    }

    public function update(User $actor, Site $site): bool
    {
        return $actor->is($site->user);
    }

    public function delete(User $actor, Site $site): bool
    {
        return $actor->is($site->user);
    }
}
