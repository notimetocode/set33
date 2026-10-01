<?php

namespace App\Actions\Site;

use App\Actions\AiService\GenerateAiServiceContent;
use App\Enums\SearchConsoleDimension;
use App\Models\AiService;
use App\Models\Site;
use App\Support\SiteSyncMetrics;
use Carbon\CarbonImmutable;

class PreprocessSiteAiReport
{
    public function __construct(
        private BuildSiteAiReportPreprocessPrompt $buildPrompt,
        private GenerateAiServiceContent $generateContent,
        private ParseSiteAiReportPreprocessReply $parseReply,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     items: list<array<string, mixed>>,
     *     message: string|null,
     *     retryable: bool,
     *     model: string|null,
     *     usage: array<string, int|null>|null,
     *     period: array{from: string, to: string}
     * }
     */
    public function handle(
        Site $site,
        AiService $aiService,
        string $from,
        string $to,
    ): array {
        $analytics = $this->loadAnalytics($site, $from, $to);
        $searchConsole = $this->loadSearchConsole($site, $from, $to);
        $commits = $this->loadCommits($site, $from, $to);
        $coverage = $this->buildCoverage($site, $from, $to, $analytics);
        $candidateUrls = $this->loadCandidateUrls($site, $from, $to);
        $labSnapshots = $this->loadLabSnapshots($site);
        $cruxSnapshots = $this->loadCruxSnapshots($site);

        if ($analytics === [] && $searchConsole === [] && $commits === []
            && $candidateUrls === [] && $labSnapshots === [] && $cruxSnapshots === []) {
            return $this->failure(
                'Нет данных за выбранный период. Загрузите метрики на вкладке «Данные сервисов» и повторите попытку.',
                $from,
                $to,
            );
        }

        $prompt = $this->buildPrompt->handle(
            $site,
            $from,
            $to,
            $analytics,
            $searchConsole,
            $commits,
            $coverage,
            $candidateUrls,
            $labSnapshots,
            $cruxSnapshots,
        );

        $result = $this->generateContent->handle($aiService, $prompt);

        if (! $result['ok'] || ! filled($result['reply'])) {
            return [
                'ok' => false,
                'items' => [],
                'message' => $result['message'] ?? 'Не удалось выполнить предварительную обработку.',
                'retryable' => (bool) ($result['retryable'] ?? false),
                'model' => $result['model'],
                'usage' => $result['usage'],
                'period' => ['from' => $from, 'to' => $to],
            ];
        }

        $parsed = $this->parseReply->handle((string) $result['reply']);

        if (! $parsed['ok']) {
            return [
                'ok' => false,
                'items' => [],
                'message' => $parsed['message'] ?? 'Не удалось разобрать ответ AI.',
                'retryable' => false,
                'model' => $result['model'],
                'usage' => $result['usage'],
                'period' => ['from' => $from, 'to' => $to],
            ];
        }

        return [
            'ok' => true,
            'items' => $this->filterItems(
                $parsed['items'],
                $site,
                $commits,
                $labSnapshots,
                $cruxSnapshots,
            ),
            'message' => null,
            'retryable' => false,
            'model' => $result['model'],
            'usage' => $result['usage'],
            'period' => ['from' => $from, 'to' => $to],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $commits
     * @param  list<array<string, mixed>>  $labSnapshots
     * @param  list<array<string, mixed>>  $cruxSnapshots
     * @return list<array<string, mixed>>
     */
    private function filterItems(
        array $items,
        Site $site,
        array $commits,
        array $labSnapshots,
        array $cruxSnapshots,
    ): array {
        $commitsById = [];
        foreach ($commits as $commit) {
            $commitsById[$commit['id']] = $commit;
        }

        $labById = [];
        foreach ($labSnapshots as $snapshot) {
            $labById[$snapshot['id']] = $snapshot;
        }

        $cruxById = [];
        foreach ($cruxSnapshots as $snapshot) {
            $cruxById[$snapshot['id']] = $snapshot;
        }

        $filtered = [];
        $seen = [];

        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            $normalized = null;

            if (ParseSiteAiReportPreprocessReply::isCommitType($type)) {
                $normalized = $this->filterCommitItem($item, $commitsById);
            } elseif (ParseSiteAiReportPreprocessReply::isUrlType($type)) {
                $normalized = $this->filterUrlItem($item, $site);
            } elseif ($type === ParseSiteAiReportPreprocessReply::TYPE_PAGESPEED_LAB_DETAILS) {
                $normalized = $this->filterSnapshotItem($item, $labById, $type);
            } elseif ($type === ParseSiteAiReportPreprocessReply::TYPE_CRUX_DETAILS) {
                $normalized = $this->filterSnapshotItem($item, $cruxById, $type);
            } elseif (ParseSiteAiReportPreprocessReply::isPeriodOnlyType($type)) {
                $normalized = $this->filterPeriodOnlyItem($item);
            }

            if ($normalized === null) {
                continue;
            }

            if (isset($seen[$normalized['key']])) {
                continue;
            }

            $seen[$normalized['key']] = true;
            $filtered[] = $normalized;
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, array<string, mixed>>  $commitsById
     * @return array<string, mixed>|null
     */
    private function filterCommitItem(array $item, array $commitsById): ?array
    {
        $commit = $commitsById[$item['commit_id']] ?? null;

        if ($commit === null) {
            return null;
        }

        $suggestedSha = strtolower((string) $item['sha']);
        $commitSha = strtolower((string) $commit['sha']);

        if ($suggestedSha !== $commitSha
            && ! str_starts_with($commitSha, $suggestedSha)
            && ! str_starts_with($suggestedSha, $commitSha)) {
            return null;
        }

        $type = (string) $item['type'];

        if ($type === ParseSiteAiReportPreprocessReply::TYPE_GITHUB_COMMIT_FILES && $commit['has_files']) {
            return null;
        }

        if ($type === ParseSiteAiReportPreprocessReply::TYPE_GITHUB_COMMIT_FILES_COMPLETE
            && (! $commit['has_files'] || ! $commit['files_incomplete'])) {
            return null;
        }

        return [
            'key' => $type.':'.$commit['id'],
            'type' => $type,
            'reason' => (string) ($item['reason'] ?? ''),
            'title' => trim(($commit['short_sha'] ?? '').' '.($commit['message'] ?? '')) ?: $commit['short_sha'],
            'commit_id' => $commit['id'],
            'sha' => $commit['sha'],
            'short_sha' => $commit['short_sha'],
            'message' => $commit['message'],
            'author_date' => $commit['author_date'],
            'url' => null,
            'snapshot_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function filterUrlItem(array $item, Site $site): ?array
    {
        $url = (string) ($item['url'] ?? '');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $siteHost = strtolower((string) (parse_url((string) $site->url, PHP_URL_HOST) ?: ''));

        if ($host === '' || $siteHost === '') {
            return null;
        }

        $normalize = static fn (string $value): string => preg_replace('/^www\./', '', $value) ?? $value;

        if ($normalize($host) !== $normalize($siteHost)) {
            return null;
        }

        $type = (string) $item['type'];

        return [
            'key' => $type.':'.$url,
            'type' => $type,
            'reason' => (string) ($item['reason'] ?? ''),
            'title' => $url,
            'commit_id' => null,
            'sha' => null,
            'url' => $url,
            'snapshot_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, array<string, mixed>>  $byId
     * @return array<string, mixed>|null
     */
    private function filterSnapshotItem(array $item, array $byId, string $type): ?array
    {
        $snapshot = $byId[$item['snapshot_id']] ?? null;

        if ($snapshot === null || ($snapshot['include_details_in_report'] ?? false)) {
            return null;
        }

        return [
            'key' => $type.':'.$snapshot['id'],
            'type' => $type,
            'reason' => (string) ($item['reason'] ?? ''),
            'title' => (string) ($snapshot['label'] ?? ('#'.$snapshot['id'])),
            'commit_id' => null,
            'sha' => null,
            'url' => $snapshot['url'] ?? null,
            'snapshot_id' => $snapshot['id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function filterPeriodOnlyItem(array $item): array
    {
        $type = (string) $item['type'];

        return [
            'key' => $type,
            'type' => $type,
            'reason' => (string) ($item['reason'] ?? ''),
            'title' => $type,
            'commit_id' => null,
            'sha' => null,
            'url' => null,
            'snapshot_id' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $analytics
     * @return array<string, mixed>
     */
    private function buildCoverage(Site $site, string $from, string $to, array $analytics): array
    {
        $optionalMissing = false;

        foreach ($analytics as $row) {
            foreach (SiteSyncMetrics::ANALYTICS_OPTIONAL as $field) {
                if (! array_key_exists($field, $row) || $row[$field] === null) {
                    $optionalMissing = true;
                    break 2;
                }
            }
        }

        $hasDimensions = $site->searchConsoleDimensions()
            ->where('period_from', $from)
            ->where('period_to', $to)
            ->whereIn('dimension', [
                SearchConsoleDimension::Query->value,
                SearchConsoleDimension::Page->value,
                SearchConsoleDimension::Device->value,
                SearchConsoleDimension::Country->value,
            ])
            ->exists();

        $hasAppearances = $site->searchConsoleDimensions()
            ->where('period_from', $from)
            ->where('period_to', $to)
            ->where('dimension', SearchConsoleDimension::SearchAppearance->value)
            ->exists();

        $hasPageBreakdown = $site->analyticsPages()
            ->where('period_from', $from)
            ->where('period_to', $to)
            ->exists();

        return [
            'analytics_days' => count($analytics),
            'ga_optional_metrics_missing' => $optionalMissing || $analytics === [],
            'ga_page_breakdown' => $hasPageBreakdown,
            'gsc_dimensions' => $hasDimensions,
            'gsc_search_appearances' => $hasAppearances,
            'gsc_sitemaps' => $site->searchConsoleSitemaps()->exists(),
            'gsc_url_inspections' => $site->urlInspections()->exists(),
            'website_homepage_fetched_at' => $site->web_data_fetched_at?->toIso8601String(),
            'page_snapshots_count' => $site->pageSnapshots()->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadAnalytics(Site $site, string $from, string $to): array
    {
        return $site->analyticsDaily()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->toDateString(),
                'sessions' => $row->sessions,
                'total_users' => $row->total_users,
                'screen_page_views' => $row->screen_page_views,
                'organic_sessions' => $row->organic_sessions,
                'engagement_rate' => $row->engagement_rate,
                'bounce_rate' => $row->bounce_rate,
                'engaged_sessions' => $row->engaged_sessions,
                'average_session_duration' => $row->average_session_duration,
                'event_count' => $row->event_count,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadSearchConsole(Site $site, string $from, string $to): array
    {
        return $site->searchConsoleDaily()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->toDateString(),
                'clicks' => $row->clicks,
                'impressions' => $row->impressions,
                'ctr' => $row->ctr,
                'position' => $row->position,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadCommits(Site $site, string $from, string $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->endOfDay();

        return $site->githubCommits()
            ->whereBetween('author_date', [$fromDate, $toDate])
            ->orderByDesc('author_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'sha' => (string) $row->sha,
                'short_sha' => substr((string) $row->sha, 0, 7),
                'message' => $row->message,
                'author_name' => $row->author_name,
                'author_date' => $row->author_date?->toIso8601String(),
                'has_files' => $row->hasFiles(),
                'files_incomplete' => $row->hasFiles() ? (bool) $row->files_incomplete : false,
            ])
            ->all();
    }

    /**
     * @return list<array{url: string, clicks: int|null, source: string}>
     */
    private function loadCandidateUrls(Site $site, string $from, string $to): array
    {
        $urls = [];

        $pages = $site->searchConsoleDimensions()
            ->where('period_from', $from)
            ->where('period_to', $to)
            ->where('dimension', SearchConsoleDimension::Page)
            ->orderBy('rank')
            ->limit(25)
            ->get(['value', 'clicks']);

        foreach ($pages as $page) {
            $url = trim((string) $page->value);
            if ($url === '') {
                continue;
            }
            $urls[$url] = [
                'url' => $url,
                'clicks' => (int) $page->clicks,
                'source' => 'gsc_pages',
            ];
        }

        foreach ($site->urlInspections()->orderByDesc('inspected_at')->limit(25)->get(['inspected_url']) as $inspection) {
            $url = trim((string) $inspection->inspected_url);
            if ($url === '' || isset($urls[$url])) {
                continue;
            }
            $urls[$url] = [
                'url' => $url,
                'clicks' => null,
                'source' => 'url_inspections',
            ];
        }

        return array_values($urls);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadLabSnapshots(Site $site): array
    {
        return $site->pagespeedLabSnapshots()
            ->orderByDesc('fetched_at')
            ->limit(40)
            ->get(['id', 'url', 'strategy', 'performance_score', 'seo_score', 'include_details_in_report', 'fetched_at'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'url' => $row->url,
                'strategy' => $row->strategy,
                'performance_score' => $row->performance_score,
                'seo_score' => $row->seo_score,
                'include_details_in_report' => (bool) $row->include_details_in_report,
                'label' => trim(($row->strategy ?: '').' '.$row->url),
                'fetched_at' => $row->fetched_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadCruxSnapshots(Site $site): array
    {
        return $site->cruxSnapshots()
            ->orderByDesc('fetched_at')
            ->limit(40)
            ->get(['id', 'scope', 'url', 'form_factor', 'overall_category', 'include_details_in_report', 'fetched_at'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'scope' => $row->scope,
                'url' => $row->url,
                'form_factor' => $row->form_factor,
                'overall_category' => $row->overall_category,
                'include_details_in_report' => (bool) $row->include_details_in_report,
                'label' => trim(($row->scope ?: '').' '.($row->form_factor ?: '').' '.$row->url),
                'fetched_at' => $row->fetched_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array{
     *     ok: bool,
     *     items: list<array<string, mixed>>,
     *     message: string|null,
     *     retryable: bool,
     *     model: null,
     *     usage: null,
     *     period: array{from: string, to: string}
     * }
     */
    private function failure(string $message, string $from, string $to): array
    {
        return [
            'ok' => false,
            'items' => [],
            'message' => $message,
            'retryable' => false,
            'model' => null,
            'usage' => null,
            'period' => ['from' => $from, 'to' => $to],
        ];
    }
}
