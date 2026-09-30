<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Localization;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string, locale?: string}  $data
     */
    public function handle(array $data): User
    {
        $name = trim($data['name']);
        $locale = $data['locale'] ?? Localization::defaultLocale();

        if (! Localization::isSupported($locale)) {
            $locale = Localization::defaultLocale();
        }

        $user = User::query()->create([
            'name' => $name,
            'first_name' => $name,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::User,
            'locale' => $locale,
        ]);

        Event::dispatch(new Registered($user));

        return $user;
    }
}
