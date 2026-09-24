<?php

namespace App\Policies\App;

use App\Models\User;

class GithubConnectionPolicy
{
    public function view(User $actor): bool
    {
        return true;
    }

    public function connect(User $actor): bool
    {
        return true;
    }

    public function disconnect(User $actor): bool
    {
        return true;
    }
}
