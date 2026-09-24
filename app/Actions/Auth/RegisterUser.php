<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $name = trim($data['name']);

        $user = User::query()->create([
            'name' => $name,
            'first_name' => $name,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::User,
        ]);

        Event::dispatch(new Registered($user));

        return $user;
    }
}
