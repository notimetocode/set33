<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteSearchConsoleSitemap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSearchConsoleSitemap>
 */
class SiteSearchConsoleSitemapFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'path' => 'https://example.com/sitemap.xml',
            'type' => 'SITEMAP',
            'is_pending' => false,
            'is_sitemaps_index' => false,
            'last_downloaded_at' => now()->subDay(),
            'last_submitted_at' => now()->subDays(7),
            'errors' => 0,
            'warnings' => 0,
            'contents' => [
                ['type' => 'WEB', 'submitted' => 100],
            ],
        ];
    }
}
