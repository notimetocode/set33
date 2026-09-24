<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteSearchConsoleDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSearchConsoleDaily>
 */
class SiteSearchConsoleDailyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'date' => fake()->unique()->date(),
            'clicks' => fake()->numberBetween(0, 1000),
            'impressions' => fake()->numberBetween(0, 20000),
            'ctr' => fake()->randomFloat(4, 0, 0.5),
            'position' => fake()->randomFloat(2, 1, 50),
        ];
    }
}
