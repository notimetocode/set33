<?php

namespace App\Actions\PageSpeed;

use App\Enums\SitePageSpeedIntegrationStatus;
use App\Models\SiteCruxSnapshot;
use App\Models\SitePageSpeedIntegration;
use App\Models\SitePageSpeedLabSnapshot;
use App\Services\PageSpeed\PageSpeedApiClient;
use App\Support\SiteSyncMetrics;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncSitePageSpeedMetrics
{
    public function __construct(
        private readonly PageSpeedApiClient $pageSpeedApiClient,
    ) {}

    /**
     * @param  list<string>|null  $metrics
     */
    public function handle(SitePageSpeedIntegration $integration, ?array $metrics = null): SitePageSpeedIntegration
    {
        $integration->loadMissing(['site', 'googleConnection']);
        $connection = $integration->googleConnection;

        if ($connection === null || $connection->needsReauth()) {
            throw new RuntimeException('Интеграция не привязана к аккаунту Google.');
        }

        $selected = SiteSyncMetrics::resolvePageSpeed($metrics);

        if ($selected === []) {
            throw new RuntimeException('Выберите хотя бы одну метрику PageSpeed / CrUX.');
        }

        $siteUrl = (string) ($integration->site?->url ?? '');

        if ($siteUrl === '') {
            throw new RuntimeException('У сайта не указан URL.');
        }

        $replaceExisting = $metrics !== null;
        $needsLab = in_array('psi_lab', $selected, true);
        $needsCruxOrigin = in_array('crux_origin', $selected, true);
        $needsCruxUrl = in_array('crux_url', $selected, true);

        try {
            DB::transaction(function () use (
                $integration,
                $siteUrl,
                $replaceExisting,
                $needsLab,
                $needsCruxOrigin,
                $needsCruxUrl,
            ): void {
                if ($replaceExisting && $needsLab) {
                    SitePageSpeedLabSnapshot::query()
                        ->where('site_id', $integration->site_id)
                        ->delete();
                }

                if ($replaceExisting && ($needsCruxOrigin || $needsCruxUrl)) {
                    SiteCruxSnapshot::query()
                        ->where('site_id', $integration->site_id)
                        ->delete();
                }

                foreach ($integration->strategy->runStrategies() as $strategy) {
                    $row = $this->pageSpeedApiClient->runAudit($siteUrl, $strategy);

                    if ($needsLab) {
                        SitePageSpeedLabSnapshot::query()->create([
                            'site_id' => $integration->site_id,
                            'url' => $row['url'],
                            'strategy' => $row['strategy'],
                            'fetched_at' => now(),
                            'performance_score' => $row['performance_score'],
                            'lcp_ms' => $row['lcp_ms'],
                            'inp_ms' => $row['inp_ms'],
                            'cls' => $row['cls'],
                            'fcp_ms' => $row['fcp_ms'],
                            'ttfb_ms' => $row['ttfb_ms'],
                            'tbt_ms' => $row['tbt_ms'],
                            'speed_index_ms' => $row['speed_index_ms'],
                        ]);
                    }

                    if ($needsCruxOrigin && $row['crux_origin'] !== null) {
                        $this->storeCruxRow($integration->site_id, $row['crux_origin']);
                    }

                    if ($needsCruxUrl && $row['crux_url'] !== null) {
                        $this->storeCruxRow($integration->site_id, $row['crux_url']);
                    }
                }

                $integration->forceFill([
                    'status' => SitePageSpeedIntegrationStatus::Active,
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
            });
        } catch (Throwable $e) {
            $integration->forceFill([
                'status' => SitePageSpeedIntegrationStatus::Error,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }

        return $integration->refresh()->load('googleConnection');
    }

    /**
     * @param  array{
     *     scope: string,
     *     url: string,
     *     form_factor: string,
     *     collection_period_start: ?string,
     *     collection_period_end: ?string,
     *     lcp_p75_ms: ?int,
     *     inp_p75_ms: ?int,
     *     cls_p75: ?float,
     *     fcp_p75_ms: ?int,
     *     ttfb_p75_ms: ?int
     * }  $row
     */
    private function storeCruxRow(int $siteId, array $row): void
    {
        SiteCruxSnapshot::query()->create([
            'site_id' => $siteId,
            'scope' => $row['scope'],
            'url' => $row['url'] !== '' ? $row['url'] : 'unknown',
            'form_factor' => $row['form_factor'],
            'collection_period_start' => $row['collection_period_start'],
            'collection_period_end' => $row['collection_period_end'],
            'lcp_p75_ms' => $row['lcp_p75_ms'],
            'inp_p75_ms' => $row['inp_p75_ms'],
            'cls_p75' => $row['cls_p75'],
            'fcp_p75_ms' => $row['fcp_p75_ms'],
            'ttfb_p75_ms' => $row['ttfb_p75_ms'],
            'fetched_at' => now(),
        ]);
    }
}
