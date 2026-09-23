<?php

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class IssueApiToken
{
    public function handle(User $user, string $tokenName, array $abilities = ['*']): NewAccessToken
    {
        return $user->createToken($tokenName, $abilities);
    }
}
