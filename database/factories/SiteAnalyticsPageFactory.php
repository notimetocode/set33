<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteAnalyticsPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteAnalyticsPage>
 */
class SiteAnalyticsPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'period_from' => now()->subDays(7)->toDateString(),
            'period_to' => now()->subDay()->toDateString(),
            'page_path' => '/'.fake()->slug(),
            'rank' => fake()->numberBetween(1, 50),
            'sessions' => fake()->numberBetween(0, 500),
            'screen_page_views' => fake()->numberBetween(0, 1000),
            'total_users' => fake()->numberBetween(0, 400),
        ];
    }
}
