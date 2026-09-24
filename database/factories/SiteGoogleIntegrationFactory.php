<?php

namespace Database\Factories;

use App\Enums\SiteGoogleIntegrationStatus;
use App\Models\GoogleConnection;
use App\Models\Site;
use App\Models\SiteGoogleIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteGoogleIntegration>
 */
class SiteGoogleIntegrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'google_connection_id' => GoogleConnection::factory(),
            'ga4_property_id' => 'properties/'.fake()->numerify('########'),
            'gsc_site_url' => 'https://'.fake()->domainName().'/',
            'status' => SiteGoogleIntegrationStatus::Active,
            'last_synced_at' => null,
            'last_error' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'ga4_property_id' => null,
            'gsc_site_url' => null,
            'status' => SiteGoogleIntegrationStatus::Pending,
        ]);
    }
}
