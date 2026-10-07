<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class IssueGoogleLoginCode
{
    private const TTL_SECONDS = 60;

    public function handle(User $user): string
    {
        $code = Str::random(64);

        Cache::put($this->cacheKey($code), $user->id, now()->addSeconds(self::TTL_SECONDS));

        return $code;
    }

    private function cacheKey(string $code): string
    {
        return 'google_login:'.$code;
    }
}
