<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SitePageSpeedLabSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitePageSpeedLabSnapshot>
 */
class SitePageSpeedLabSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'url' => 'https://example.com/',
            'strategy' => 'mobile',
            'fetched_at' => now(),
            'performance_score' => fake()->numberBetween(40, 100),
            'lcp_ms' => fake()->numberBetween(1000, 4000),
            'inp_ms' => fake()->numberBetween(50, 300),
            'cls' => fake()->randomFloat(3, 0, 0.3),
            'fcp_ms' => fake()->numberBetween(500, 2500),
            'ttfb_ms' => fake()->numberBetween(100, 800),
            'tbt_ms' => fake()->numberBetween(50, 600),
            'speed_index_ms' => fake()->numberBetween(1000, 5000),
        ];
    }
}
