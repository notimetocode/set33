<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteAnalyticsDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteAnalyticsDaily>
 */
class SiteAnalyticsDailyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'date' => fake()->unique()->date(),
            'sessions' => fake()->numberBetween(0, 5000),
            'total_users' => fake()->numberBetween(0, 4000),
            'new_users' => fake()->numberBetween(0, 2000),
            'screen_page_views' => fake()->numberBetween(0, 10000),
            'organic_sessions' => fake()->numberBetween(0, 2000),
            'organic_total_users' => fake()->numberBetween(0, 1500),
            'organic_new_users' => fake()->numberBetween(0, 800),
        ];
    }
}
