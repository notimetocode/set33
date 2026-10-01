<?php

namespace App\Actions\Site;

use App\Actions\Github\FetchSiteGithubCommitFiles;
use App\Actions\Google\InspectSiteUrls;
use App\Actions\Google\MergeSiteGaOptionalMetrics;
use App\Actions\Google\SyncSiteAnalyticsPageBreakdown;
use App\Actions\Google\SyncSiteGoogleMetrics;
use App\Actions\PageSpeed\AuditSitePageSpeedUrl;
use App\Models\Site;
use App\Models\SiteCruxSnapshot;
use App\Models\SiteGithubCommit;
use App\Models\SitePageSpeedLabSnapshot;
use App\Support\SiteSyncMetrics;
use Illuminate\Support\Carbon;
use Throwable;

class ApplySiteAiReportPreprocessItems
{
    public function __construct(
        private FetchSiteGithubCommitFiles $fetchCommitFiles,
        private InspectSiteUrls $inspectSiteUrls,
        private MergeSiteGaOptionalMetrics $mergeGaOptionalMetrics,
        private SyncSiteAnalyticsPageBreakdown $syncAnalyticsPages,
        private SyncSiteGoogleMetrics $syncGoogleMetrics,
        private AuditSitePageSpeedUrl $auditPageSpeedUrl,
        private CollectSitePageSnapshot $collectPageSnapshot,
        private CollectSiteWebData $collectSiteWebData,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     ok: bool,
     *     results: list<array{key: string, ok: bool, message: string|null}>,
     *     message: string|null
     * }
     */
    public function handle(Site $site, string $from, string $to, array $items): array
    {
        $results = [];
        $failed = 0;

        foreach ($items as $item) {
            $key = (string) ($item['key'] ?? $this->fallbackKey($item));
            $type = (string) ($item['type'] ?? '');

            try {
                $this->applyItem($site, $from, $to, $item);
                $results[] = [
                    'key' => $key,
                    'ok' => true,
                    'message' => null,
                ];
            } catch (Throwable $exception) {
                $failed++;
                $results[] = [
                    'key' => $key,
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'ok' => $failed === 0,
            'results' => $results,
            'message' => $failed === 0
                ? null
                : ($failed === count($items)
                    ? 'Не удалось довыгрузить выбранные данные.'
                    : 'Часть выбранных данных не удалось довыгрузить.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyItem(Site $site, string $from, string $to, array $item): void
    {
        $type = (string) ($item['type'] ?? '');

        match ($type) {
            ParseSiteAiReportPreprocessReply::TYPE_GITHUB_COMMIT_FILES => $this->applyGithubCommitFiles($site, $item, false),
            ParseSiteAiReportPreprocessReply::TYPE_GITHUB_COMMIT_FILES_COMPLETE => $this->applyGithubCommitFiles($site, $item, true),
            ParseSiteAiReportPreprocessReply::TYPE_GSC_URL_INSPECTION => $this->inspectSiteUrls->handle(
                $site,
                [(string) $item['url']],
                $from,
                $to,
            ),
            ParseSiteAiReportPreprocessReply::TYPE_GSC_DIMENSIONS => $this->syncGoogle(
                $site,
                $from,
                $to,
                SiteSyncMetrics::SEARCH_CONSOLE_DIMENSIONS_DEFAULT,
            ),
            ParseSiteAiReportPreprocessReply::TYPE_GSC_SEARCH_APPEARANCES => $this->syncGoogle(
                $site,
                $from,
                $to,
                ['search_appearances'],
            ),
            ParseSiteAiReportPreprocessReply::TYPE_GSC_SITEMAPS => $this->syncGoogle(
                $site,
                $from,
                $to,
                ['sitemaps'],
            ),
            ParseSiteAiReportPreprocessReply::TYPE_GA_OPTIONAL_METRICS => $this->mergeGaOptionalMetrics->handle($site, $from, $to),
            ParseSiteAiReportPreprocessReply::TYPE_GA_PAGE_BREAKDOWN => $this->syncAnalyticsPages->handle($site, $from, $to),
            ParseSiteAiReportPreprocessReply::TYPE_PAGESPEED_LAB_URL => $this->auditPageSpeedUrl->handle($site, (string) $item['url']),
            ParseSiteAiReportPreprocessReply::TYPE_PAGESPEED_LAB_DETAILS => $this->markLabDetails((int) $item['snapshot_id'], $site),
            ParseSiteAiReportPreprocessReply::TYPE_CRUX_DETAILS => $this->markCruxDetails((int) $item['snapshot_id'], $site),
            ParseSiteAiReportPreprocessReply::TYPE_WEBSITE_HOMEPAGE_REFRESH => $this->collectSiteWebData->handle($site),
            ParseSiteAiReportPreprocessReply::TYPE_WEBSITE_PAGE_SNAPSHOT => $this->collectPageSnapshot->handle($site, (string) $item['url']),
            default => throw new \RuntimeException('Неизвестный тип довыгрузки: '.$type),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyGithubCommitFiles(Site $site, array $item, bool $complete): void
    {
        $commit = SiteGithubCommit::query()
            ->where('site_id', $site->id)
            ->where('id', (int) $item['commit_id'])
            ->firstOrFail();

        $this->fetchCommitFiles->handle($site, $commit, $complete);
    }

    /**
     * @param  list<string>  $metrics
     */
    private function syncGoogle(Site $site, string $from, string $to, array $metrics): void
    {
        $integration = $site->googleIntegration;

        if ($integration === null) {
            throw new \RuntimeException('Google-интеграция не настроена.');
        }

        $this->syncGoogleMetrics->handle(
            $integration,
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->startOfDay(),
            $metrics,
        );
    }

    private function markLabDetails(int $snapshotId, Site $site): void
    {
        $snapshot = SitePageSpeedLabSnapshot::query()
            ->where('site_id', $site->id)
            ->where('id', $snapshotId)
            ->firstOrFail();

        $snapshot->forceFill(['include_details_in_report' => true])->save();
    }

    private function markCruxDetails(int $snapshotId, Site $site): void
    {
        $snapshot = SiteCruxSnapshot::query()
            ->where('site_id', $site->id)
            ->where('id', $snapshotId)
            ->firstOrFail();

        $snapshot->forceFill(['include_details_in_report' => true])->save();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function fallbackKey(array $item): string
    {
        $type = (string) ($item['type'] ?? 'unknown');

        if (isset($item['commit_id'])) {
            return $type.':'.$item['commit_id'];
        }

        if (isset($item['snapshot_id'])) {
            return $type.':'.$item['snapshot_id'];
        }

        if (isset($item['url'])) {
            return $type.':'.$item['url'];
        }

        return $type;
    }
}
