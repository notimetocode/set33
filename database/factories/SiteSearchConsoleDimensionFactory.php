<?php

namespace Database\Factories;

use App\Enums\SearchConsoleDimension;
use App\Models\Site;
use App\Models\SiteSearchConsoleDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSearchConsoleDimension>
 */
class SiteSearchConsoleDimensionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-07',
            'dimension' => SearchConsoleDimension::Query,
            'value' => fake()->words(3, true),
            'rank' => fake()->numberBetween(1, 20),
            'clicks' => fake()->numberBetween(0, 500),
            'impressions' => fake()->numberBetween(0, 10000),
            'ctr' => fake()->randomFloat(4, 0, 0.5),
            'position' => fake()->randomFloat(2, 1, 50),
        ];
    }
}
