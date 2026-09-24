<?php

namespace App\Policies\App;

use App\Models\AiService;
use App\Models\User;

class AiServicePolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, AiService $aiService): bool
    {
        return $actor->is($aiService->user);
    }

    public function create(User $actor): bool
    {
        return true;
    }

    public function update(User $actor, AiService $aiService): bool
    {
        return $actor->is($aiService->user);
    }

    public function delete(User $actor, AiService $aiService): bool
    {
        return $actor->is($aiService->user);
    }

    public function check(User $actor, AiService $aiService): bool
    {
        return $actor->is($aiService->user);
    }

    public function generate(User $actor, AiService $aiService): bool
    {
        return $actor->is($aiService->user);
    }
}
