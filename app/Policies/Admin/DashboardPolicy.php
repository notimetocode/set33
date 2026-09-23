<?php

namespace App\Policies\Admin;

use App\Models\User;

class DashboardPolicy
{
    public function view(User $actor): bool
    {
        return $actor->isAdmin();
    }
}
