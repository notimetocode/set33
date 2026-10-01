<?php

namespace App\Actions\PageSpeed;

use App\Enums\SitePageSpeedIntegrationStatus;
use App\Models\Site;
use App\Models\SiteCruxSnapshot;
use App\Models\SitePageSpeedLabSnapshot;
use App\Services\PageSpeed\PageSpeedApiClient;
use RuntimeException;

class AuditSitePageSpeedUrl
{
    public function __construct(
        private readonly PageSpeedApiClient $pageSpeedApiClient,
    ) {}

    /**
     * @return list<SitePageSpeedLabSnapshot>
     */
    public function handle(Site $site, string $url): array
    {
        $integration = $site->pagespeedIntegration;

        if ($integration === null) {
            throw new RuntimeException('Chrome UX Report / PageSpeed не подключён к сайту.');
        }

        $integration->loadMissing('googleConnection');

        if ($integration->googleConnection === null || $integration->googleConnection->needsReauth()) {
            throw new RuntimeException('Интеграция не привязана к аккаунту Google.');
        }

        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Некорректный URL для PageSpeed.');
        }

        $created = [];

        try {
            foreach ($integration->strategy->runStrategies() as $strategy) {
                $row = $this->pageSpeedApiClient->runAudit($url, $strategy);

                $created[] = SitePageSpeedLabSnapshot::query()->create([
                    'site_id' => $site->id,
                    'url' => $row['url'],
                    'strategy' => $row['strategy'],
                    'fetched_at' => now(),
                    'performance_score' => $row['performance_score'],
                    'accessibility_score' => $row['accessibility_score'],
                    'best_practices_score' => $row['best_practices_score'],
                    'seo_score' => $row['seo_score'],
                    'lcp_ms' => $row['lcp_ms'],
                    'inp_ms' => $row['inp_ms'],
                    'cls' => $row['cls'],
                    'fcp_ms' => $row['fcp_ms'],
                    'ttfb_ms' => $row['ttfb_ms'],
                    'tbt_ms' => $row['tbt_ms'],
                    'speed_index_ms' => $row['speed_index_ms'],
                    'payload' => $row['payload'],
                    'include_details_in_report' => false,
                ]);

                if ($row['crux_url'] !== null) {
                    $this->storeCruxRow($site->id, $row['crux_url']);
                }

                if ($row['crux_origin'] !== null) {
                    $this->storeCruxRow($site->id, $row['crux_origin']);
                }
            }

            $integration->forceFill([
                'status' => SitePageSpeedIntegrationStatus::Active,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $integration->forceFill([
                'status' => SitePageSpeedIntegrationStatus::Error,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }

        return $created;
    }

    /**
     * @param  array{
     *     scope: string,
     *     url: string,
     *     form_factor: string,
     *     overall_category: ?string,
     *     collection_period_start: ?string,
     *     collection_period_end: ?string,
     *     lcp_p75_ms: ?int,
     *     inp_p75_ms: ?int,
     *     cls_p75: ?float,
     *     fcp_p75_ms: ?int,
     *     ttfb_p75_ms: ?int,
     *     metrics: ?array<string, mixed>
     * }  $row
     */
    private function storeCruxRow(int $siteId, array $row): void
    {
        SiteCruxSnapshot::query()->updateOrCreate(
            [
                'site_id' => $siteId,
                'scope' => $row['scope'],
                'url' => $row['url'] !== '' ? $row['url'] : 'unknown',
                'form_factor' => $row['form_factor'],
            ],
            [
                'overall_category' => $row['overall_category'],
                'collection_period_start' => $row['collection_period_start'],
                'collection_period_end' => $row['collection_period_end'],
                'lcp_p75_ms' => $row['lcp_p75_ms'],
                'inp_p75_ms' => $row['inp_p75_ms'],
                'cls_p75' => $row['cls_p75'],
                'fcp_p75_ms' => $row['fcp_p75_ms'],
                'ttfb_p75_ms' => $row['ttfb_p75_ms'],
                'metrics' => $row['metrics'],
                'fetched_at' => now(),
            ],
        );
    }
}
