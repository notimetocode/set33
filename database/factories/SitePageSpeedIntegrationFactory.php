<?php

namespace Database\Factories;

use App\Enums\PageSpeedStrategy;
use App\Enums\SitePageSpeedIntegrationStatus;
use App\Models\GoogleConnection;
use App\Models\Site;
use App\Models\SitePageSpeedIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitePageSpeedIntegration>
 */
class SitePageSpeedIntegrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'google_connection_id' => GoogleConnection::factory(),
            'strategy' => PageSpeedStrategy::Mobile,
            'status' => SitePageSpeedIntegrationStatus::Active,
            'last_synced_at' => null,
            'last_error' => null,
        ];
    }
}
