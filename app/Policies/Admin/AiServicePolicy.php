<?php

namespace App\Policies\Admin;

use App\Models\AiService;
use App\Models\User;

class AiServicePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, AiService $aiService): bool
    {
        return $actor->isAdmin() && $aiService->is_global;
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, AiService $aiService): bool
    {
        return $actor->isAdmin() && $aiService->is_global;
    }

    public function delete(User $actor, AiService $aiService): bool
    {
        return $actor->isAdmin() && $aiService->is_global;
    }

    public function check(User $actor, AiService $aiService): bool
    {
        return $actor->isAdmin() && $aiService->is_global;
    }
}
