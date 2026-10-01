<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SitePageSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitePageSnapshot>
 */
class SitePageSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = 'https://example.com/'.fake()->slug();

        return [
            'site_id' => Site::factory(),
            'url' => $url,
            'final_url' => $url,
            'page_title' => fake()->sentence(4),
            'meta_description' => fake()->sentence(12),
            'meta_keywords' => null,
            'og_title' => fake()->sentence(4),
            'og_description' => fake()->sentence(10),
            'og_image_url' => null,
            'canonical_url' => $url,
            'html_lang' => 'en',
            'fetched_at' => now(),
            'error' => null,
        ];
    }
}
