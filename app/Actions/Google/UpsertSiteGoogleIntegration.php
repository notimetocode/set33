<?php

namespace App\Actions\Google;

use App\Enums\SiteGoogleIntegrationStatus;
use App\Models\Site;
use App\Models\SiteGoogleIntegration;
use App\Models\User;
use InvalidArgumentException;

class UpsertSiteGoogleIntegration
{
    /**
     * @param  array{ga4_property_id?: string|null, gsc_site_url?: string|null}  $data
     */
    public function handle(User $user, Site $site, array $data): SiteGoogleIntegration
    {
        $connection = $user->googleConnection;

        if ($connection === null) {
            throw new InvalidArgumentException('Сначала подключите аккаунт Google.');
        }

        $ga4 = isset($data['ga4_property_id']) ? trim((string) $data['ga4_property_id']) : null;
        $gsc = isset($data['gsc_site_url']) ? trim((string) $data['gsc_site_url']) : null;

        if ($ga4 === '') {
            $ga4 = null;
        }

        if ($gsc === '') {
            $gsc = null;
        }

        if ($ga4 === null && $gsc === null) {
            throw new InvalidArgumentException('Выберите property GA4 и/или сайт Search Console.');
        }

        if ($ga4 !== null && ! str_starts_with($ga4, 'properties/')) {
            $ga4 = 'properties/'.$ga4;
        }

        $integration = SiteGoogleIntegration::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'google_connection_id' => $connection->id,
                'ga4_property_id' => $ga4,
                'gsc_site_url' => $gsc,
                'status' => SiteGoogleIntegrationStatus::Active,
                'last_error' => null,
            ],
        );

        return $integration->load('googleConnection');
    }
}
