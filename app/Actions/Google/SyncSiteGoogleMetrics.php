<?php

namespace App\Actions\Google;

use App\Enums\SearchConsoleDimension;
use App\Enums\SiteGoogleIntegrationStatus;
use App\Models\GoogleConnection;
use App\Models\SiteAnalyticsDaily;
use App\Models\SiteGoogleIntegration;
use App\Models\SiteSearchConsoleDaily;
use App\Models\SiteSearchConsoleDimension;
use App\Models\SiteSearchConsoleSitemap;
use App\Models\SiteUrlInspection;
use App\Services\Google\GoogleApiClient;
use App\Support\SiteSyncMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncSiteGoogleMetrics
{
    public function __construct(
        private readonly GoogleApiClient $googleApiClient,
    ) {}

    /**
     * @param  list<string>|null  $metrics  null = дефолтный набор (jobs/backfill), без wipe; иначе wipe + выбранные метрики
     * @param  array{queries?: int, pages?: int, url_inspections?: int}  $limits
     */
    public function handle(
        SiteGoogleIntegration $integration,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array $metrics = null,
        array $limits = [],
    ): SiteGoogleIntegration {
        $integration->loadMissing(['site', 'googleConnection']);

        $connection = $integration->googleConnection;

        if ($connection === null) {
            throw new RuntimeException('Интеграция не привязана к аккаунту Google.');
        }

        $endDate ??= now()->subDay()->startOfDay();
        $startDate ??= $endDate->copy();
        $selected = SiteSyncMetrics::resolveGoogle($metrics);
        $replaceExisting = $metrics !== null;
        $dimensionLimits = SiteSyncMetrics::resolveDimensionLimits($limits);

        if ($selected === []) {
            throw new RuntimeException('Выберите хотя бы одну метрику для загрузки.');
        }

        try {
            DB::transaction(function () use ($integration, $connection, $startDate, $endDate, $selected, $replaceExisting, $dimensionLimits): void {
                $analyticsMetrics = SiteSyncMetrics::selectedIn($selected, SiteSyncMetrics::ANALYTICS);
                $gscDailyMetrics = SiteSyncMetrics::selectedIn($selected, SiteSyncMetrics::SEARCH_CONSOLE_DAILY);
                $gscDimensionKeys = SiteSyncMetrics::selectedIn($selected, SiteSyncMetrics::SEARCH_CONSOLE_DIMENSIONS);
                $syncSitemaps = in_array('sitemaps', $selected, true);
                $syncUrlInspections = in_array('url_inspections', $selected, true);

                if ($analyticsMetrics !== [] && filled($integration->ga4_property_id)) {
                    if ($replaceExisting) {
                        SiteAnalyticsDaily::query()
                            ->where('site_id', $integration->site_id)
                            ->delete();
                    }

                    $rows = $this->googleApiClient->fetchAnalyticsDaily(
                        $connection,
                        $integration->ga4_property_id,
                        $startDate,
                        $endDate,
                        $analyticsMetrics,
                    );

                    foreach ($rows as $row) {
                        $payload = $this->emptyAnalyticsPayload();

                        foreach ($analyticsMetrics as $metric) {
                            $payload[$metric] = $row[$metric] ?? (SiteSyncMetrics::isAnalyticsInteger($metric) ? 0 : 0.0);
                        }

                        if ($replaceExisting) {
                            SiteAnalyticsDaily::query()->create([
                                'site_id' => $integration->site_id,
                                'date' => $row['date'],
                                ...$payload,
                            ]);
                        } else {
                            SiteAnalyticsDaily::query()->updateOrCreate(
                                [
                                    'site_id' => $integration->site_id,
                                    'date' => $row['date'],
                                ],
                                $payload,
                            );
                        }
                    }
                }

                if ($gscDailyMetrics !== [] && filled($integration->gsc_site_url)) {
                    if ($replaceExisting) {
                        SiteSearchConsoleDaily::query()
                            ->where('site_id', $integration->site_id)
                            ->delete();
                    }

                    $rows = $this->googleApiClient->fetchSearchConsoleDaily(
                        $connection,
                        $integration->gsc_site_url,
                        $startDate,
                        $endDate,
                    );

                    foreach ($rows as $row) {
                        $payload = [
                            'clicks' => 0,
                            'impressions' => 0,
                            'ctr' => 0,
                            'position' => 0,
                        ];

                        foreach ($gscDailyMetrics as $metric) {
                            $payload[$metric] = $row[$metric] ?? 0;
                        }

                        if ($replaceExisting) {
                            SiteSearchConsoleDaily::query()->create([
                                'site_id' => $integration->site_id,
                                'date' => $row['date'],
                                ...$payload,
                            ]);
                        } else {
                            SiteSearchConsoleDaily::query()->updateOrCreate(
                                [
                                    'site_id' => $integration->site_id,
                                    'date' => $row['date'],
                                ],
                                $payload,
                            );
                        }
                    }
                }

                if ($gscDimensionKeys !== [] && filled($integration->gsc_site_url)) {
                    if ($replaceExisting) {
                        SiteSearchConsoleDimension::query()
                            ->where('site_id', $integration->site_id)
                            ->delete();
                    }

                    $this->syncSearchConsoleDimensions(
                        $integration,
                        $connection,
                        $startDate,
                        $endDate,
                        $gscDimensionKeys,
                        $replaceExisting,
                        $dimensionLimits,
                    );
                }

                if ($syncSitemaps && filled($integration->gsc_site_url)) {
                    $this->syncSitemaps($integration, $connection, $replaceExisting);
                }

                if ($syncUrlInspections && filled($integration->gsc_site_url)) {
                    $this->syncUrlInspections(
                        $integration,
                        $connection,
                        $startDate,
                        $endDate,
                        $replaceExisting,
                        $dimensionLimits['url_inspections'],
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

    /**
     * @return array<string, int|float>
     */
    private function emptyAnalyticsPayload(): array
    {
        return [
            'sessions' => 0,
            'total_users' => 0,
            'new_users' => 0,
            'screen_page_views' => 0,
            'organic_sessions' => 0,
            'organic_total_users' => 0,
            'organic_new_users' => 0,
            'engaged_sessions' => 0,
            'engagement_rate' => 0.0,
            'bounce_rate' => 0.0,
            'average_session_duration' => 0.0,
            'event_count' => 0,
            'organic_engaged_sessions' => 0,
        ];
    }

    /**
     * @param  list<string>  $dimensionKeys
     * @param  array{queries: int, pages: int}  $limits
     */
    private function syncSearchConsoleDimensions(
        SiteGoogleIntegration $integration,
        GoogleConnection $connection,
        Carbon $startDate,
        Carbon $endDate,
        array $dimensionKeys,
        bool $replaceExisting,
        array $limits,
    ): void {
        $periodFrom = $startDate->toDateString();
        $periodTo = $endDate->toDateString();

        $dimensions = $this->googleApiClient->fetchSearchConsoleDimensions(
            $connection,
            $integration->gsc_site_url,
            $startDate,
            $endDate,
            $dimensionKeys,
            $limits,
        );

        if (! $replaceExisting) {
            SiteSearchConsoleDimension::query()
                ->where('site_id', $integration->site_id)
                ->where('period_from', $periodFrom)
                ->where('period_to', $periodTo)
                ->whereIn('dimension', $this->dimensionEnumValues($dimensionKeys))
                ->delete();
        }

        $payload = [
            SearchConsoleDimension::Query->value => $dimensions['queries'] ?? [],
            SearchConsoleDimension::Page->value => $dimensions['pages'] ?? [],
            SearchConsoleDimension::Device->value => $dimensions['devices'] ?? [],
            SearchConsoleDimension::Country->value => $dimensions['countries'] ?? [],
            SearchConsoleDimension::SearchAppearance->value => $dimensions['search_appearances'] ?? [],
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

    private function syncSitemaps(
        SiteGoogleIntegration $integration,
        GoogleConnection $connection,
        bool $replaceExisting,
    ): void {
        if ($replaceExisting) {
            SiteSearchConsoleSitemap::query()
                ->where('site_id', $integration->site_id)
                ->delete();
        }

        $rows = $this->googleApiClient->fetchSitemaps(
            $connection,
            $integration->gsc_site_url,
        );

        foreach ($rows as $row) {
            if ($replaceExisting) {
                SiteSearchConsoleSitemap::query()->create([
                    'site_id' => $integration->site_id,
                    'path' => $row['path'],
                    'type' => $row['type'],
                    'is_pending' => $row['is_pending'],
                    'is_sitemaps_index' => $row['is_sitemaps_index'],
                    'last_downloaded_at' => $row['last_downloaded_at'],
                    'last_submitted_at' => $row['last_submitted_at'],
                    'errors' => $row['errors'],
                    'warnings' => $row['warnings'],
                    'contents' => $row['contents'],
                ]);
            } else {
                SiteSearchConsoleSitemap::query()->updateOrCreate(
                    [
                        'site_id' => $integration->site_id,
                        'path' => $row['path'],
                    ],
                    [
                        'type' => $row['type'],
                        'is_pending' => $row['is_pending'],
                        'is_sitemaps_index' => $row['is_sitemaps_index'],
                        'last_downloaded_at' => $row['last_downloaded_at'],
                        'last_submitted_at' => $row['last_submitted_at'],
                        'errors' => $row['errors'],
                        'warnings' => $row['warnings'],
                        'contents' => $row['contents'],
                    ],
                );
            }
        }
    }

    private function syncUrlInspections(
        SiteGoogleIntegration $integration,
        GoogleConnection $connection,
        Carbon $startDate,
        Carbon $endDate,
        bool $replaceExisting,
        int $limit,
    ): void {
        if ($replaceExisting) {
            SiteUrlInspection::query()
                ->where('site_id', $integration->site_id)
                ->delete();
        }

        $urls = $this->resolveUrlsForInspection(
            $connection,
            $integration,
            $startDate,
            $endDate,
            $limit,
        );

        if ($urls === []) {
            return;
        }

        $rows = $this->googleApiClient->inspectUrls(
            $connection,
            $integration->gsc_site_url,
            $urls,
        );

        $periodFrom = $startDate->toDateString();
        $periodTo = $endDate->toDateString();

        foreach ($rows as $row) {
            if ($replaceExisting) {
                SiteUrlInspection::query()->create([
                    'site_id' => $integration->site_id,
                    'inspected_url' => $row['inspected_url'],
                    'period_from' => $periodFrom,
                    'period_to' => $periodTo,
                    'verdict' => $row['verdict'],
                    'coverage_state' => $row['coverage_state'],
                    'indexing_state' => $row['indexing_state'],
                    'page_fetch_state' => $row['page_fetch_state'],
                    'robots_txt_state' => $row['robots_txt_state'],
                    'crawled_as' => $row['crawled_as'],
                    'last_crawl_time' => $row['last_crawl_time'],
                    'google_canonical' => $row['google_canonical'],
                    'user_canonical' => $row['user_canonical'],
                    'inspection_result_link' => $row['inspection_result_link'],
                    'referring_urls' => $row['referring_urls'],
                    'sitemaps' => $row['sitemaps'],
                    'inspected_at' => now(),
                ]);
            } else {
                SiteUrlInspection::query()->updateOrCreate(
                    [
                        'site_id' => $integration->site_id,
                        'inspected_url' => $row['inspected_url'],
                    ],
                    [
                        'period_from' => $periodFrom,
                        'period_to' => $periodTo,
                        'verdict' => $row['verdict'],
                        'coverage_state' => $row['coverage_state'],
                        'indexing_state' => $row['indexing_state'],
                        'page_fetch_state' => $row['page_fetch_state'],
                        'robots_txt_state' => $row['robots_txt_state'],
                        'crawled_as' => $row['crawled_as'],
                        'last_crawl_time' => $row['last_crawl_time'],
                        'google_canonical' => $row['google_canonical'],
                        'user_canonical' => $row['user_canonical'],
                        'inspection_result_link' => $row['inspection_result_link'],
                        'referring_urls' => $row['referring_urls'],
                        'sitemaps' => $row['sitemaps'],
                        'inspected_at' => now(),
                    ],
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveUrlsForInspection(
        GoogleConnection $connection,
        SiteGoogleIntegration $integration,
        Carbon $startDate,
        Carbon $endDate,
        int $limit,
    ): array {
        $dimensions = $this->googleApiClient->fetchSearchConsoleDimensions(
            $connection,
            $integration->gsc_site_url,
            $startDate,
            $endDate,
            ['pages'],
            ['pages' => $limit],
        );

        $urls = [];

        foreach ($dimensions['pages'] ?? [] as $row) {
            $url = trim((string) ($row['value'] ?? ''));

            if ($url === '' || in_array($url, $urls, true)) {
                continue;
            }

            $urls[] = $url;

            if (count($urls) >= $limit) {
                break;
            }
        }

        if ($urls === [] && filled($integration->site?->url)) {
            $urls[] = (string) $integration->site->url;
        }

        return $urls;
    }

    /**
     * @param  list<string>  $dimensionKeys
     * @return list<string>
     */
    private function dimensionEnumValues(array $dimensionKeys): array
    {
        $map = [
            'queries' => SearchConsoleDimension::Query->value,
            'pages' => SearchConsoleDimension::Page->value,
            'devices' => SearchConsoleDimension::Device->value,
            'countries' => SearchConsoleDimension::Country->value,
            'search_appearances' => SearchConsoleDimension::SearchAppearance->value,
        ];

        $values = [];

        foreach ($dimensionKeys as $key) {
            if (isset($map[$key])) {
                $values[] = $map[$key];
            }
        }

        return $values;
    }
}
