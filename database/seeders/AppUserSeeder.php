<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AppUserSeeder extends Seeder
{
    private const EMAIL = 'user@example.com';

    private const CITY_NAME = 'Гродно';

    public function run(): void
    {
        $city = City::query()->where('name', self::CITY_NAME)->first();

        if ($city === null) {
            throw new RuntimeException('Город «'.self::CITY_NAME.'» не найден. Сначала запустите CitySeeder.');
        }

        User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Пользователь',
                'last_name' => 'Пользователев',
                'first_name' => 'Иван',
                'middle_name' => 'Иванович',
                'gender' => Gender::Male,
                'birth_date' => '1992-01-01',
                'phone' => '+375292222222',
                'telegram' => null,
                'viber' => null,
                'city_id' => $city->id,
                'profile_visibility' => [
                    'last_name' => true,
                    'birth_date' => true,
                    'phone' => false,
                    'telegram' => false,
                    'viber' => false,
                ],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => UserRole::User,
            ],
        );
    }
}
