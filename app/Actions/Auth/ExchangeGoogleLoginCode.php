<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ExchangeGoogleLoginCode
{
    public function handle(string $code): User
    {
        $userId = Cache::pull('google_login:'.$code);

        if (! $userId) {
            throw ValidationException::withMessages([
                'code' => [__('auth.google_code_invalid')],
            ]);
        }

        $user = User::query()->find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => [__('auth.google_code_invalid')],
            ]);
        }

        return $user;
    }
}
