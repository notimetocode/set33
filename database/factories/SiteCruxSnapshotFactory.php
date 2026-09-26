<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteCruxSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteCruxSnapshot>
 */
class SiteCruxSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'scope' => 'origin',
            'url' => 'https://example.com',
            'form_factor' => 'PHONE',
            'collection_period_start' => now()->subDays(28)->toDateString(),
            'collection_period_end' => now()->subDay()->toDateString(),
            'lcp_p75_ms' => fake()->numberBetween(1500, 4000),
            'inp_p75_ms' => fake()->numberBetween(50, 300),
            'cls_p75' => fake()->randomFloat(3, 0, 0.25),
            'fcp_p75_ms' => fake()->numberBetween(800, 2500),
            'ttfb_p75_ms' => fake()->numberBetween(200, 1000),
            'fetched_at' => now(),
        ];
    }
}
