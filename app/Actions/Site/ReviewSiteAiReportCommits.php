<?php

namespace App\Actions\Site;

use App\Actions\AiService\GenerateAiServiceContent;
use App\Models\AiService;
use App\Models\Site;

class ReviewSiteAiReportCommits
{
    public function __construct(
        private BuildSiteAiReportCommitReviewPrompt $buildPrompt,
        private GenerateAiServiceContent $generateContent,
    ) {}

    /**
     * @param  list<int>  $commitIds
     * @return array{
     *     ok: bool,
     *     reply: string|null,
     *     message: string|null,
     *     retryable: bool,
     *     model: string|null,
     *     usage: array<string, int|null>|null,
     *     period: array{from: string, to: string},
     *     commit_ids: list<int>
     * }
     */
    public function handle(
        Site $site,
        AiService $aiService,
        string $from,
        string $to,
        array $commitIds,
    ): array {
        $commitIds = array_values(array_unique(array_map('intval', $commitIds)));

        $commits = $this->loadCommits($site, $commitIds);

        if ($commits === []) {
            return [
                'ok' => false,
                'reply' => null,
                'message' => 'Нет коммитов с выгруженными diffs для ревью.',
                'retryable' => false,
                'model' => null,
                'usage' => null,
                'period' => ['from' => $from, 'to' => $to],
                'commit_ids' => [],
            ];
        }

        $searchConsole = $this->loadSearchConsole($site, $from, $to);
        $prompt = $this->buildPrompt->handle($site, $from, $to, $searchConsole, $commits);
        $result = $this->generateContent->handle($aiService, $prompt);

        if (! $result['ok'] || ! filled($result['reply'])) {
            return [
                'ok' => false,
                'reply' => null,
                'message' => $result['message'] ?? 'Не удалось выполнить ревью коммитов.',
                'retryable' => (bool) ($result['retryable'] ?? false),
                'model' => $result['model'],
                'usage' => $result['usage'],
                'period' => ['from' => $from, 'to' => $to],
                'commit_ids' => array_column($commits, 'id'),
            ];
        }

        return [
            'ok' => true,
            'reply' => trim((string) $result['reply']),
            'message' => null,
            'retryable' => false,
            'model' => $result['model'],
            'usage' => $result['usage'],
            'period' => ['from' => $from, 'to' => $to],
            'commit_ids' => array_column($commits, 'id'),
        ];
    }

    /**
     * @param  list<int>  $commitIds
     * @return list<array<string, mixed>>
     */
    private function loadCommits(Site $site, array $commitIds): array
    {
        if ($commitIds === []) {
            return [];
        }

        return $site->githubCommits()
            ->whereIn('id', $commitIds)
            ->whereNotNull('files_fetched_at')
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
                'stats' => is_array($row->stats) ? $row->stats : null,
                'files' => is_array($row->files) ? $row->files : [],
                'files_incomplete' => (bool) $row->files_incomplete,
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
}
