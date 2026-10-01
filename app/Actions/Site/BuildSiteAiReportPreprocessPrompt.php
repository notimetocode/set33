<?php

namespace App\Actions\Site;

use App\Models\Site;

class BuildSiteAiReportPreprocessPrompt
{
    private const MAX_COMMITS = 80;

    private const MAX_ANALYTICS_DAYS = 90;

    private const MAX_SEARCH_CONSOLE_DAYS = 90;

    private const MAX_CANDIDATE_URLS = 30;

    private const MAX_SNAPSHOTS = 40;

    /**
     * @param  list<array<string, mixed>>  $analytics
     * @param  list<array<string, mixed>>  $searchConsole
     * @param  list<array<string, mixed>>  $commits
     * @param  array<string, mixed>  $coverage
     * @param  list<array<string, mixed>>  $candidateUrls
     * @param  list<array<string, mixed>>  $labSnapshots
     * @param  list<array<string, mixed>>  $cruxSnapshots
     */
    public function handle(
        Site $site,
        string $from,
        string $to,
        array $analytics,
        array $searchConsole,
        array $commits,
        array $coverage = [],
        array $candidateUrls = [],
        array $labSnapshots = [],
        array $cruxSnapshots = [],
    ): string {
        $sections = [
            $this->instructions(),
            $this->siteContext($site, $from, $to),
            $this->coverageSection($coverage),
            $this->analyticsSection($analytics),
            $this->searchConsoleSection($searchConsole),
            $this->commitsSection($commits),
            $this->candidateUrlsSection($candidateUrls),
            $this->labSnapshotsSection($labSnapshots),
            $this->cruxSnapshotsSection($cruxSnapshots),
            $this->outputSchema(),
        ];

        return implode("\n\n", $sections);
    }

    private function instructions(): string
    {
        return <<<'TXT'
Ты — помощник по подготовке данных для SEO-отчёта.

Задача: по метаданным периода решить, какие данные стоит дополнительно выгрузить перед генерацией отчёта.
Предлагай только то, чего ещё нет или чего не хватает для качественного анализа. Не предлагай всё подряд.
Если довыгрузка не нужна — верни пустой массив items.
Ответ — ТОЛЬКО валидный JSON по схеме ниже, без markdown и пояснений вне JSON.
TXT;
    }

    private function siteContext(Site $site, string $from, string $to): string
    {
        $payload = [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'domain' => $site->domain ?? parse_url((string) $site->url, PHP_URL_HOST),
                'url' => $site->url,
            ],
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
        ];

        return "Контекст сайта и периода:\n".$this->json($payload);
    }

    /**
     * @param  array<string, mixed>  $coverage
     */
    private function coverageSection(array $coverage): string
    {
        if ($coverage === []) {
            return 'Покрытие данных: неизвестно.';
        }

        return "Покрытие уже загруженных данных:\n".$this->json($coverage);
    }

    /**
     * @param  list<array<string, mixed>>  $analytics
     */
    private function analyticsSection(array $analytics): string
    {
        $rows = array_slice($analytics, 0, self::MAX_ANALYTICS_DAYS);

        if ($rows === []) {
            return 'Google Analytics (daily): данных нет.';
        }

        return 'Google Analytics (daily, до '.self::MAX_ANALYTICS_DAYS." дней):\n".$this->json($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $searchConsole
     */
    private function searchConsoleSection(array $searchConsole): string
    {
        $rows = array_slice($searchConsole, 0, self::MAX_SEARCH_CONSOLE_DAYS);

        if ($rows === []) {
            return 'Search Console (daily): данных нет.';
        }

        return 'Search Console (daily, до '.self::MAX_SEARCH_CONSOLE_DAYS." дней):\n".$this->json($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $commits
     */
    private function commitsSection(array $commits): string
    {
        $rows = array_slice($commits, 0, self::MAX_COMMITS);

        if ($rows === []) {
            return 'GitHub commits: за период нет коммитов.';
        }

        return 'GitHub commits (метаданные):\n'.$this->json($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $candidateUrls
     */
    private function candidateUrlsSection(array $candidateUrls): string
    {
        $rows = array_slice($candidateUrls, 0, self::MAX_CANDIDATE_URLS);

        if ($rows === []) {
            return 'Кандидаты URL (GSC pages / inspections): нет.';
        }

        return "Кандидаты URL для inspection / PageSpeed / page snapshot:\n".$this->json($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $labSnapshots
     */
    private function labSnapshotsSection(array $labSnapshots): string
    {
        $rows = array_slice($labSnapshots, 0, self::MAX_SNAPSHOTS);

        if ($rows === []) {
            return 'PageSpeed lab snapshots: нет.';
        }

        return "PageSpeed lab snapshots (без payload):\n".$this->json($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $cruxSnapshots
     */
    private function cruxSnapshotsSection(array $cruxSnapshots): string
    {
        $rows = array_slice($cruxSnapshots, 0, self::MAX_SNAPSHOTS);

        if ($rows === []) {
            return 'CrUX snapshots: нет.';
        }

        return "CrUX snapshots (без полного metrics):\n".$this->json($rows);
    }

    private function outputSchema(): string
    {
        return <<<'TXT'
Схема ответа (строго):
{
  "items": [
    { "type": "github_commit_files", "commit_id": 42, "sha": "полный sha", "reason": "..." },
    { "type": "github_commit_files_complete", "commit_id": 43, "sha": "полный sha", "reason": "..." },
    { "type": "gsc_url_inspection", "url": "https://...", "reason": "..." },
    { "type": "gsc_dimensions", "reason": "..." },
    { "type": "gsc_search_appearances", "reason": "..." },
    { "type": "gsc_sitemaps", "reason": "..." },
    { "type": "ga_optional_metrics", "reason": "..." },
    { "type": "ga_page_breakdown", "reason": "..." },
    { "type": "pagespeed_lab_url", "url": "https://...", "reason": "..." },
    { "type": "pagespeed_lab_details", "snapshot_id": 12, "reason": "..." },
    { "type": "crux_details", "snapshot_id": 5, "reason": "..." },
    { "type": "website_homepage_refresh", "reason": "..." },
    { "type": "website_page_snapshot", "url": "https://...", "reason": "..." }
  ]
}

Правила выбора типа:
- github_commit_files — только коммиты с has_files=false
- github_commit_files_complete — только коммиты с has_files=true и files_incomplete=true
- gsc_url_inspection / pagespeed_lab_url / website_page_snapshot — URL из кандидатов или логичный URL сайта
- gsc_dimensions / gsc_search_appearances / gsc_sitemaps / ga_optional_metrics / ga_page_breakdown / website_homepage_refresh — без url/commit_id; предлагай если coverage говорит что данных нет или они устарели
- pagespeed_lab_details / crux_details — snapshot_id из списков выше, и только если include_details_in_report=false
- reason: 1 короткое предложение на русском
TXT;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }
}
