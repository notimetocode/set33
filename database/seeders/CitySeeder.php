<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * @var list<array{name: string, region: string|null}>
     */
    private const REGIONAL_CENTERS = [
        ['name' => 'Минск', 'region' => null],
        ['name' => 'Брест', 'region' => 'Брестская область'],
        ['name' => 'Витебск', 'region' => 'Витебская область'],
        ['name' => 'Гомель', 'region' => 'Гомельская область'],
        ['name' => 'Гродно', 'region' => 'Гродненская область'],
        ['name' => 'Могилёв', 'region' => 'Могилёвская область'],
    ];

    public function run(): void
    {
        foreach (self::REGIONAL_CENTERS as $city) {
            City::query()->updateOrCreate(
                ['name' => $city['name']],
                ['region' => $city['region']],
            );
        }
    }
}
