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
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     reply: string|null,
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
        $commits = $this->loadCommits($site, $from, $to);
        $events = $this->loadEvents($site, $from, $to);

        $dataCounts = [
            'analytics' => count($analytics),
            'search_console' => count($searchConsole),
            'search_console_queries' => count($searchConsoleDimensions['queries']),
            'search_console_pages' => count($searchConsoleDimensions['pages']),
            'search_console_devices' => count($searchConsoleDimensions['devices']),
            'search_console_countries' => count($searchConsoleDimensions['countries']),
            'github_commits' => count($commits),
            'events' => count($events),
        ];

        if ($dataCounts['analytics'] === 0
            && $dataCounts['search_console'] === 0
            && $dataCounts['search_console_queries'] === 0
            && $dataCounts['search_console_pages'] === 0
            && $dataCounts['search_console_devices'] === 0
            && $dataCounts['search_console_countries'] === 0
            && $dataCounts['github_commits'] === 0
        ) {
            return [
                'ok' => false,
                'reply' => null,
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
        );

        $result = $this->generateContent->handle($aiService, $prompt);

        return [
            'ok' => $result['ok'],
            'reply' => $result['reply'],
            'message' => $result['message'],
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
     *     countries: list<array<string, mixed>>
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
