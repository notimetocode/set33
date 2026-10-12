<?php

namespace App\Actions\Site;

use App\Models\Site;

class BuildSiteAiReportCommitReviewPrompt
{
    private const MAX_SEARCH_CONSOLE_DAYS = 90;

    private const MAX_COMMITS_WITH_FILES = 25;

    private const MAX_FILES_PER_COMMIT = 40;

    private const MAX_PATCH_CHARS = 1200;

    /**
     * @param  list<array<string, mixed>>  $searchConsole
     * @param  list<array<string, mixed>>  $commits
     */
    public function handle(
        Site $site,
        string $from,
        string $to,
        array $searchConsole,
        array $commits,
    ): string {
        $sections = [
            $this->instructions(),
            $this->siteContext($site, $from, $to),
            $this->searchConsoleSection($searchConsole),
            $this->commitsSection($commits),
        ];

        return implode("\n\n", $sections);
    }

    private function instructions(): string
    {
        return <<<'TXT'
Ты — SEO-аналитик. Нужно связать изменения в коде с сильными сдвигами в Google Search Console.

Задача:
1. По daily Search Console найди дни/участки, где clicks, impressions, CTR или position сильно изменились относительно соседних дней.
2. Сопоставь эти точки с коммитами (дата author_date и содержимое diffs).
3. По каждому значимому коммиту (или группе близких) дай гипотезу влияния на SEO: что могло вырасти/упасть и почему, или явно напиши «скорее не связано».
4. Не выдумывай метрики и файлы, которых нет в данных. Если GSC пуст — скажи, что без Search Console корреляция невозможна, и кратко опиши риски по diffs.

Формат ответа — связный текст на русском (можно с подзаголовками и списками). Без JSON и без markdown-ограждений ```.
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
        if ($commits === []) {
            return 'GitHub commits с diffs: нет.';
        }

        $included = array_slice($commits, 0, self::MAX_COMMITS_WITH_FILES);
        $note = count($commits) > self::MAX_COMMITS_WITH_FILES
            ? 'Показаны '.count($included).' из '.count($commits)." коммитов (самые свежие).\n"
            : '';

        $simplified = [];

        foreach ($included as $row) {
            $item = [
                'id' => $row['id'] ?? null,
                'sha' => $row['short_sha'] ?? (isset($row['sha']) ? substr((string) $row['sha'], 0, 7) : null),
                'message' => $this->firstLine((string) ($row['message'] ?? '')),
                'author' => $row['author_name'] ?? null,
                'date' => $row['author_date'] ?? null,
            ];

            $stats = is_array($row['stats'] ?? null) ? $row['stats'] : null;
            if ($stats !== null) {
                $item['stats'] = [
                    'additions' => (int) ($stats['additions'] ?? 0),
                    'deletions' => (int) ($stats['deletions'] ?? 0),
                    'total' => (int) ($stats['total'] ?? 0),
                ];
            }

            $item['files_incomplete'] = (bool) ($row['files_incomplete'] ?? false);

            $files = is_array($row['files'] ?? null) ? $row['files'] : [];
            $item['files'] = array_map(function (array $file): array {
                $entry = [
                    'filename' => $file['filename'] ?? null,
                    'status' => $file['status'] ?? null,
                    'additions' => (int) ($file['additions'] ?? 0),
                    'deletions' => (int) ($file['deletions'] ?? 0),
                ];

                if (isset($file['previous_filename']) && is_string($file['previous_filename']) && $file['previous_filename'] !== '') {
                    $entry['previous_filename'] = $file['previous_filename'];
                }

                $patch = isset($file['patch']) && is_string($file['patch']) ? $file['patch'] : null;
                if ($patch !== null && $patch !== '') {
                    $entry['patch'] = $this->truncate($patch, self::MAX_PATCH_CHARS);
                }

                return $entry;
            }, array_slice($files, 0, self::MAX_FILES_PER_COMMIT));

            $simplified[] = $item;
        }

        return "GitHub commits с diffs:\n"
            .$note
            ."Поля: id, sha, message, author, date, stats?, files_incomplete, files (filename, status, additions, deletions, previous_filename?, patch?).\n"
            .$this->json($simplified);
    }

    private function firstLine(string $message): string
    {
        $line = strtok(str_replace(["\r\n", "\r"], "\n", $message), "\n");

        return is_string($line) ? trim($line) : '';
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max).'…';
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }
}
