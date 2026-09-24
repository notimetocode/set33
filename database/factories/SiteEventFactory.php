<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteEvent>
 */
class SiteEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'occurred_on' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'title' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'url' => fake()->optional()->url(),
        ];
    }
}
