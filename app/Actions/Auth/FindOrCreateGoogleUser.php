<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Localization;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

class FindOrCreateGoogleUser
{
    /**
     * @param  array{google_id: string, email: string, name: string}  $profile
     */
    public function handle(array $profile, ?string $locale = null): User
    {
        $locale ??= Localization::defaultLocale();

        if (! Localization::isSupported($locale)) {
            $locale = Localization::defaultLocale();
        }

        $byGoogleId = User::query()->where('google_id', $profile['google_id'])->first();

        if ($byGoogleId) {
            return $byGoogleId;
        }

        $email = mb_strtolower(trim($profile['email']));
        $byEmail = User::query()->where('email', $email)->first();

        if ($byEmail) {
            $byEmail->forceFill([
                'google_id' => $profile['google_id'],
                'email_verified_at' => $byEmail->email_verified_at ?? now(),
            ])->save();

            return $byEmail->refresh();
        }

        $name = trim($profile['name']);

        if ($name === '') {
            $name = (string) strtok($email, '@');
        }

        $user = User::query()->create([
            'name' => $name,
            'first_name' => $name,
            'email' => $email,
            'google_id' => $profile['google_id'],
            'password' => null,
            'role' => UserRole::User,
            'locale' => $locale,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        Event::dispatch(new Registered($user));

        return $user->refresh();
    }
}
