<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    private const EMAIL = 'admin@example.com';

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
                'name' => 'Администратор',
                'last_name' => 'Админов',
                'first_name' => 'Админ',
                'middle_name' => 'Админович',
                'gender' => Gender::Male,
                'birth_date' => '1990-01-01',
                'phone' => '+375291111111',
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
                'role' => UserRole::Admin,
            ],
        );
    }
}
