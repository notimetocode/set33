<?php

namespace App\Actions\Google;

use App\Enums\SearchConsoleDimension;
use App\Enums\SiteGoogleIntegrationStatus;
use App\Models\GoogleConnection;
use App\Models\SiteAnalyticsDaily;
use App\Models\SiteGoogleIntegration;
use App\Models\SiteSearchConsoleDaily;
use App\Models\SiteSearchConsoleDimension;
use App\Services\Google\GoogleApiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncSiteGoogleMetrics
{
    public function __construct(
        private readonly GoogleApiClient $googleApiClient,
    ) {}

    public function handle(SiteGoogleIntegration $integration, ?Carbon $startDate = null, ?Carbon $endDate = null): SiteGoogleIntegration
    {
        $integration->loadMissing(['site', 'googleConnection']);

        $connection = $integration->googleConnection;

        if ($connection === null) {
            throw new RuntimeException('Интеграция не привязана к аккаунту Google.');
        }

        $endDate ??= now()->subDay()->startOfDay();
        $startDate ??= $endDate->copy();

        try {
            DB::transaction(function () use ($integration, $connection, $startDate, $endDate): void {
                if (filled($integration->ga4_property_id)) {
                    $rows = $this->googleApiClient->fetchAnalyticsDaily(
                        $connection,
                        $integration->ga4_property_id,
                        $startDate,
                        $endDate,
                    );

                    foreach ($rows as $row) {
                        SiteAnalyticsDaily::query()->updateOrCreate(
                            [
                                'site_id' => $integration->site_id,
                                'date' => $row['date'],
                            ],
                            [
                                'sessions' => $row['sessions'],
                                'total_users' => $row['total_users'],
                                'new_users' => $row['new_users'],
                                'screen_page_views' => $row['screen_page_views'],
                                'organic_sessions' => $row['organic_sessions'],
                                'organic_total_users' => $row['organic_total_users'],
                                'organic_new_users' => $row['organic_new_users'],
                            ],
                        );
                    }
                }

                if (filled($integration->gsc_site_url)) {
                    $rows = $this->googleApiClient->fetchSearchConsoleDaily(
                        $connection,
                        $integration->gsc_site_url,
                        $startDate,
                        $endDate,
                    );

                    foreach ($rows as $row) {
                        SiteSearchConsoleDaily::query()->updateOrCreate(
                            [
                                'site_id' => $integration->site_id,
                                'date' => $row['date'],
                            ],
                            [
                                'clicks' => $row['clicks'],
                                'impressions' => $row['impressions'],
                                'ctr' => $row['ctr'],
                                'position' => $row['position'],
                            ],
                        );
                    }

                    $this->syncSearchConsoleDimensions(
                        $integration,
                        $connection,
                        $startDate,
                        $endDate,
                    );
                }

                $integration->forceFill([
                    'status' => SiteGoogleIntegrationStatus::Active,
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
            });
        } catch (Throwable $e) {
            $integration->forceFill([
                'status' => SiteGoogleIntegrationStatus::Error,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }

        return $integration->refresh()->load('googleConnection');
    }

    public function backfill(SiteGoogleIntegration $integration): SiteGoogleIntegration
    {
        $days = max(1, (int) config('services.google.metrics_backfill_days', 28));
        $endDate = now()->subDay()->startOfDay();
        $startDate = $endDate->copy()->subDays($days - 1);

        return $this->handle($integration, $startDate, $endDate);
    }

    private function syncSearchConsoleDimensions(
        SiteGoogleIntegration $integration,
        GoogleConnection $connection,
        Carbon $startDate,
        Carbon $endDate,
    ): void {
        $periodFrom = $startDate->toDateString();
        $periodTo = $endDate->toDateString();

        $dimensions = $this->googleApiClient->fetchSearchConsoleDimensions(
            $connection,
            $integration->gsc_site_url,
            $startDate,
            $endDate,
        );

        SiteSearchConsoleDimension::query()
            ->where('site_id', $integration->site_id)
            ->where('period_from', $periodFrom)
            ->where('period_to', $periodTo)
            ->delete();

        $payload = [
            SearchConsoleDimension::Query->value => $dimensions['queries'],
            SearchConsoleDimension::Page->value => $dimensions['pages'],
            SearchConsoleDimension::Device->value => $dimensions['devices'],
            SearchConsoleDimension::Country->value => $dimensions['countries'],
        ];

        foreach ($payload as $dimension => $rows) {
            foreach (array_values($rows) as $index => $row) {
                SiteSearchConsoleDimension::query()->create([
                    'site_id' => $integration->site_id,
                    'period_from' => $periodFrom,
                    'period_to' => $periodTo,
                    'dimension' => $dimension,
                    'value' => mb_substr((string) $row['value'], 0, 2048),
                    'rank' => $index + 1,
                    'clicks' => $row['clicks'],
                    'impressions' => $row['impressions'],
                    'ctr' => $row['ctr'],
                    'position' => $row['position'],
                ]);
            }
        }
    }
}
