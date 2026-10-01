<?php

namespace App\Actions\Google;

use App\Models\Site;
use App\Models\SiteAnalyticsDaily;
use App\Services\Google\GoogleApiClient;
use App\Support\SiteSyncMetrics;
use Illuminate\Support\Carbon;
use RuntimeException;

class MergeSiteGaOptionalMetrics
{
    public function __construct(
        private readonly GoogleApiClient $googleApiClient,
    ) {}

    public function handle(Site $site, string $from, string $to): int
    {
        $integration = $site->googleIntegration;

        if ($integration === null) {
            throw new RuntimeException('Google-интеграция не настроена.');
        }

        $integration->loadMissing('googleConnection');
        $connection = $integration->googleConnection;

        if ($connection === null) {
            throw new RuntimeException('Интеграция не привязана к аккаунту Google.');
        }

        if (! filled($integration->ga4_property_id)) {
            throw new RuntimeException('Google Analytics не привязан к сайту.');
        }

        $metrics = [
            ...SiteSyncMetrics::ANALYTICS_DEFAULT,
            ...SiteSyncMetrics::ANALYTICS_OPTIONAL,
        ];

        $rows = $this->googleApiClient->fetchAnalyticsDaily(
            $connection,
            $integration->ga4_property_id,
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->startOfDay(),
            $metrics,
        );

        $updated = 0;

        foreach ($rows as $row) {
            $payload = [];

            foreach (SiteSyncMetrics::ANALYTICS_OPTIONAL as $field) {
                if (! array_key_exists($field, $row)) {
                    continue;
                }

                $payload[$field] = $row[$field];
            }

            if ($payload === []) {
                continue;
            }

            $existing = SiteAnalyticsDaily::query()
                ->where('site_id', $site->id)
                ->whereDate('date', $row['date'])
                ->first();

            if ($existing !== null) {
                $existing->forceFill($payload)->save();
                $updated++;

                continue;
            }

            $create = [
                'site_id' => $site->id,
                'date' => $row['date'],
            ];

            foreach (SiteSyncMetrics::ANALYTICS as $field) {
                $create[$field] = $row[$field] ?? (SiteSyncMetrics::isAnalyticsInteger($field) ? 0 : 0.0);
            }

            SiteAnalyticsDaily::query()->create($create);
            $updated++;
        }

        return $updated;
    }
}
