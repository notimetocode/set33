<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteUrlInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteUrlInspection>
 */
class SiteUrlInspectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'inspected_url' => 'https://example.com/'.fake()->unique()->slug(),
            'period_from' => now()->subDays(7)->toDateString(),
            'period_to' => now()->subDay()->toDateString(),
            'verdict' => 'PASS',
            'coverage_state' => 'Submitted and indexed',
            'indexing_state' => 'INDEXING_ALLOWED',
            'page_fetch_state' => 'SUCCESSFUL',
            'robots_txt_state' => 'ALLOWED',
            'crawled_as' => 'MOBILE',
            'last_crawl_time' => now()->subDay(),
            'google_canonical' => 'https://example.com/',
            'user_canonical' => 'https://example.com/',
            'inspection_result_link' => null,
            'referring_urls' => [],
            'sitemaps' => ['https://example.com/sitemap.xml'],
            'inspected_at' => now(),
        ];
    }
}
