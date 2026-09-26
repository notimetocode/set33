<?php

namespace App\Actions\Site;

use App\Actions\AiService\GenerateAiServiceContent;
use App\Enums\SearchConsoleDimension;
use App\Models\AiService;
use App\Models\Site;
use Carbon\CarbonImmutable;

class GenerateSiteAiReport
{
    public function __construct(
        private BuildSiteAiReportPrompt $buildPrompt,
        private GenerateAiServiceContent $generateContent,
        private ParseSiteAiReportReply $parseReply,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     reply: string|null,
     *     charts: list<array{
     *         id: string,
     *         type: string,
     *         title: string,
     *         labels: list<string>,
     *         series: list<array{name: string, values: list<float|int>}>
     *     }>,
     *     message: string|null,
     *     model: string|null,
     *     usage: array{
     *         prompt_tokens: int|null,
     *         candidates_tokens: int|null,
     *         total_tokens: int|null,
     *         thoughts_tokens: int|null
     *     }|null,
     *     period: array{from: string, to: string},
     *     data_counts: array{
     *         analytics: int,
     *         search_console: int,
     *         search_console_queries: int,
     *         search_console_pages: int,
     *         search_console_devices: int,
     *         search_console_countries: int,
     *         search_console_appearances: int,
     *         search_console_sitemaps: int,
     *         search_console_url_inspections: int,
     *         pagespeed_lab: int,
     *         pagespeed_crux: int,
     *         github_commits: int,
     *         events: int
     *     }
     * }
     */
    public function handle(Site $site, AiService $aiService, string $from, string $to): array
    {
        $analytics = $this->loadAnalytics($site, $from, $to);
        $searchConsole = $this->loadSearchConsole($site, $from, $to);
        $searchConsoleDimensions = $this->loadSearchConsoleDimensions($site, $from, $to);
        $pageSpeed = $this->loadPageSpeed($site);
        $commits = $this->loadCommits($site, $from, $to);
        $events = $this->loadEvents($site, $from, $to);

        $dataCounts = [
            'analytics' => count($analytics),
            'search_console' => count($searchConsole),
            'search_console_queries' => count($searchConsoleDimensions['queries']),
            'search_console_pages' => count($searchConsoleDimensions['pages']),
            'search_console_devices' => count($searchConsoleDimensions['devices']),
            'search_console_countries' => count($searchConsoleDimensions['countries']),
            'search_console_appearances' => count($searchConsoleDimensions['search_appearances']),
            'search_console_sitemaps' => count($searchConsoleDimensions['sitemaps']),
            'search_console_url_inspections' => count($searchConsoleDimensions['url_inspections']),
            'pagespeed_lab' => count($pageSpeed['lab']),
            'pagespeed_crux' => count($pageSpeed['crux']),
            'github_commits' => count($commits),
            'events' => count($events),
        ];

        if ($dataCounts['analytics'] === 0
            && $dataCounts['search_console'] === 0
            && $dataCounts['search_console_queries'] === 0
            && $dataCounts['search_console_pages'] === 0
            && $dataCounts['search_console_devices'] === 0
            && $dataCounts['search_console_countries'] === 0
            && $dataCounts['search_console_appearances'] === 0
            && $dataCounts['search_console_sitemaps'] === 0
            && $dataCounts['search_console_url_inspections'] === 0
            && $dataCounts['pagespeed_lab'] === 0
            && $dataCounts['pagespeed_crux'] === 0
            && $dataCounts['github_commits'] === 0
        ) {
            return [
                'ok' => false,
                'reply' => null,
                'charts' => [],
                'message' => 'Нет данных сервисов за выбранный период. Загрузите метрики на вкладке «Данные сервисов» и повторите попытку.',
                'model' => null,
                'usage' => null,
                'period' => ['from' => $from, 'to' => $to],
                'data_counts' => $dataCounts,
            ];
        }

        $prompt = $this->buildPrompt->handle(
            $site,
            $from,
            $to,
            $analytics,
            $searchConsole,
            $searchConsoleDimensions,
            $commits,
            $events,
            $pageSpeed,
        );

        $result = $this->generateContent->handle($aiService, $prompt);

        if (! $result['ok'] || ! filled($result['reply'])) {
            return [
                'ok' => $result['ok'],
                'reply' => $result['reply'],
                'charts' => [],
                'message' => $result['message'],
                'model' => $result['model'],
                'usage' => $result['usage'],
                'period' => ['from' => $from, 'to' => $to],
                'data_counts' => $dataCounts,
            ];
        }

        $parsed = $this->parseReply->handle((string) $result['reply']);

        return [
            'ok' => true,
            'reply' => $parsed['reply'],
            'charts' => $parsed['charts'],
            'message' => null,
            'model' => $result['model'],
            'usage' => $result['usage'],
            'period' => ['from' => $from, 'to' => $to],
            'data_counts' => $dataCounts,
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
                'new_users' => $row->new_users,
                'screen_page_views' => $row->screen_page_views,
                'organic_sessions' => $row->organic_sessions,
                'organic_total_users' => $row->organic_total_users,
                'organic_new_users' => $row->organic_new_users,
                'engaged_sessions' => $row->engaged_sessions,
                'engagement_rate' => $row->engagement_rate,
                'bounce_rate' => $row->bounce_rate,
                'average_session_duration' => $row->average_session_duration,
                'event_count' => $row->event_count,
                'organic_engaged_sessions' => $row->organic_engaged_sessions,
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
     * @return array{
     *     queries: list<array<string, mixed>>,
     *     pages: list<array<string, mixed>>,
     *     devices: list<array<string, mixed>>,
     *     countries: list<array<string, mixed>>,
     *     search_appearances: list<array<string, mixed>>,
     *     sitemaps: list<array<string, mixed>>,
     *     url_inspections: list<array<string, mixed>>
     * }
     */
    private function loadSearchConsoleDimensions(Site $site, string $from, string $to): array
    {
        $rows = $site->searchConsoleDimensions()
            ->where('period_from', $from)
            ->where('period_to', $to)
            ->orderBy('dimension')
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        $map = static fn ($row) => [
            'value' => $row->value,
            'rank' => $row->rank,
            'clicks' => $row->clicks,
            'impressions' => $row->impressions,
            'ctr' => $row->ctr,
            'position' => $row->position,
        ];

        return [
            'queries' => $rows
                ->where('dimension', SearchConsoleDimension::Query)
                ->values()
                ->map($map)
                ->all(),
            'pages' => $rows
                ->where('dimension', SearchConsoleDimension::Page)
                ->values()
                ->map($map)
                ->all(),
            'devices' => $rows
                ->where('dimension', SearchConsoleDimension::Device)
                ->values()
                ->map($map)
                ->all(),
            'countries' => $rows
                ->where('dimension', SearchConsoleDimension::Country)
                ->values()
                ->map($map)
                ->all(),
            'search_appearances' => $rows
                ->where('dimension', SearchConsoleDimension::SearchAppearance)
                ->values()
                ->map($map)
                ->all(),
            'sitemaps' => $site->searchConsoleSitemaps()
                ->orderByDesc('errors')
                ->orderByDesc('warnings')
                ->orderBy('path')
                ->get()
                ->map(fn ($row) => [
                    'path' => $row->path,
                    'type' => $row->type,
                    'is_pending' => $row->is_pending,
                    'is_sitemaps_index' => $row->is_sitemaps_index,
                    'last_downloaded_at' => $row->last_downloaded_at?->toIso8601String(),
                    'last_submitted_at' => $row->last_submitted_at?->toIso8601String(),
                    'errors' => $row->errors,
                    'warnings' => $row->warnings,
                    'contents' => $row->contents ?? [],
                ])
                ->all(),
            'url_inspections' => $site->urlInspections()
                ->orderByDesc('inspected_at')
                ->orderBy('inspected_url')
                ->get()
                ->map(fn ($row) => [
                    'inspected_url' => $row->inspected_url,
                    'verdict' => $row->verdict,
                    'coverage_state' => $row->coverage_state,
                    'indexing_state' => $row->indexing_state,
                    'page_fetch_state' => $row->page_fetch_state,
                    'robots_txt_state' => $row->robots_txt_state,
                    'crawled_as' => $row->crawled_as,
                    'last_crawl_time' => $row->last_crawl_time?->toIso8601String(),
                    'google_canonical' => $row->google_canonical,
                    'user_canonical' => $row->user_canonical,
                    'referring_urls' => $row->referring_urls ?? [],
                    'sitemaps' => $row->sitemaps ?? [],
                    'inspected_at' => $row->inspected_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{
     *     lab: list<array<string, mixed>>,
     *     crux: list<array<string, mixed>>
     * }
     */
    private function loadPageSpeed(Site $site): array
    {
        return [
            'lab' => $site->pagespeedLabSnapshots()
                ->orderByDesc('fetched_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'url' => $row->url,
                    'strategy' => $row->strategy,
                    'fetched_at' => $row->fetched_at?->toIso8601String(),
                    'performance_score' => $row->performance_score,
                    'lcp_ms' => $row->lcp_ms,
                    'inp_ms' => $row->inp_ms,
                    'cls' => $row->cls,
                    'fcp_ms' => $row->fcp_ms,
                    'ttfb_ms' => $row->ttfb_ms,
                    'tbt_ms' => $row->tbt_ms,
                    'speed_index_ms' => $row->speed_index_ms,
                ])
                ->all(),
            'crux' => $site->cruxSnapshots()
                ->orderByDesc('fetched_at')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
                ->map(fn ($row) => [
                    'scope' => $row->scope,
                    'url' => $row->url,
                    'form_factor' => $row->form_factor,
                    'collection_period_start' => $row->collection_period_start?->toDateString(),
                    'collection_period_end' => $row->collection_period_end?->toDateString(),
                    'lcp_p75_ms' => $row->lcp_p75_ms,
                    'inp_p75_ms' => $row->inp_p75_ms,
                    'cls_p75' => $row->cls_p75,
                    'fcp_p75_ms' => $row->fcp_p75_ms,
                    'ttfb_p75_ms' => $row->ttfb_p75_ms,
                    'fetched_at' => $row->fetched_at?->toIso8601String(),
                ])
                ->all(),
        ];
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
                'sha' => $row->sha,
                'short_sha' => substr($row->sha, 0, 7),
                'message' => $row->message,
                'author_name' => $row->author_name,
                'author_date' => $row->author_date?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadEvents(Site $site, string $from, string $to): array
    {
        return $site->events()
            ->whereBetween('occurred_on', [$from, $to])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->occurred_on->toDateString(),
                'title' => $row->title,
                'description' => $row->description,
                'url' => $row->url,
            ])
            ->all();
    }
}
