<?php

namespace App\Actions\Site;

use App\Enums\SearchConsoleDimension;
use App\Models\Site;

class BuildSiteAiReportPrompt
{
    private const MAX_COMMITS_IN_PROMPT = 80;

    private const MAX_EVENTS_IN_PROMPT = 50;

    /**
     * @param  list<array<string, mixed>>  $analytics
     * @param  list<array<string, mixed>>  $searchConsole
     * @param  array{
     *     queries: list<array<string, mixed>>,
     *     pages: list<array<string, mixed>>,
     *     devices: list<array<string, mixed>>,
     *     countries: list<array<string, mixed>>,
     *     search_appearances?: list<array<string, mixed>>,
     *     sitemaps?: list<array<string, mixed>>,
     *     url_inspections?: list<array<string, mixed>>
     * }  $searchConsoleDimensions
     * @param  list<array<string, mixed>>  $commits
     * @param  list<array<string, mixed>>  $events
     * @param  array{
     *     lab?: list<array<string, mixed>>,
     *     crux?: list<array<string, mixed>>
     * }  $pageSpeed
     */
    public function handle(
        Site $site,
        string $from,
        string $to,
        array $analytics,
        array $searchConsole,
        array $searchConsoleDimensions,
        array $commits,
        array $events = [],
        array $pageSpeed = [],
    ): string {
        $sections = [
            $this->instructions(),
            $this->siteContext($site, $from, $to),
            $this->analyticsSection($analytics),
            $this->searchConsoleSection($searchConsole, $searchConsoleDimensions),
            $this->pageSpeedSection($pageSpeed),
            $this->commitsSection($commits),
            $this->eventsSection($events),
        ];

        return implode("\n\n", $sections);
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Проанализируй приведённые ниже данные сайта и сформируй SEO-отчёт на русском языке.

Задачи анализа:
1. Найди закономерности, тренды и аномалии в метриках за указанный период.
2. Оцени динамику органического трафика (GA4: organic_*) и поисковой видимости (Search Console: клики, показы, CTR, средняя позиция).
3. Разбери топ-запросы и топ-страницы: что даёт клики, где высокий CTR или слабая позиция при больших показах; учти разрезы по устройствам и странам.
4. Учти типы отображения в поиске, sitemaps и URL Inspection: ошибки индексации, проблемы обхода, расхождения canonical.
5. Оцени скорость и Core Web Vitals (PageSpeed lab и CrUX field data): LCP, INP, CLS, TTFB и связанные метрики; свяжи с SEO и UX, если данные есть.
6. Сопоставь изменения метрик с активностью разработки (коммиты GitHub), если такие данные есть: возможные корреляции деплоев/изменений с ростом или падением показателей.
7. Учти ручные события периода (упоминания в СМИ, публикации, акции, инциденты и т.п.): оцени их возможное влияние на трафик и видимость.
8. Выдели сильные стороны, риски и конкретные гипотезы для улучшения SEO.
9. Если какого-то источника данных нет или он пуст — явно укажи это и не выдумывай цифры.

Структура отчёта (используй Markdown):
## Краткое резюме
## Динамика и закономерности
## Органический трафик и видимость
## Запросы, страницы, устройства и страны
## Индексация и техническое SEO
## Скорость и Core Web Vitals
## Связь с разработкой
## События и внешние факторы
## Рекомендации
## Что проверить дополнительно

Графики в тексте:
- Там, где динамика или сравнение лучше видны на графике, вставь плейсхолдер вида {{chart:id}} на отдельной строке (id: латиница, цифры и подчёркивание, например organic_trend).
- В конце ответа добавь ровно один fenced-блок с языком charts-json — массив объектов графиков (от 0 до 6 штук).
- Каждый объект: id (как в плейсхолдере), type (line|bar|pie), title (строка на русском), labels (массив строк — даты или категории), series (массив { "name": строка, "values": массив чисел той же длины, что labels }).
- Для pie используй одну серию; labels — категории.
- Бери только числа из данных выше; не выдумывай точки и не округляй так, чтобы исказить смысл.
- Не дублируй один и тот же график; не описывай charts-json в тексте отчёта — только в финальном блоке.

Пример хвоста ответа:
{{chart:organic_trend}}

```charts-json
[{"id":"organic_trend","type":"line","title":"Органические сессии","labels":["2026-09-01","2026-09-02"],"series":[{"name":"organic_sessions","values":[40,52]}]}]
```

Пиши конкретно, опирайся на числа из данных. Избегай общих фраз без привязки к периоду и метрикам.
PROMPT;
    }

    private function siteContext(Site $site, string $from, string $to): string
    {
        return implode("\n", [
            '## Контекст сайта',
            '- Название: '.$site->name,
            '- URL: '.$site->url,
            '- Период анализа: с '.$from.' по '.$to,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $analytics
     */
    private function analyticsSection(array $analytics): string
    {
        if ($analytics === []) {
            return "## Google Analytics 4\nДанные за период отсутствуют.";
        }

        return "## Google Analytics 4 (ежедневные метрики)\n"
            .'Поля: date, sessions, total_users, new_users, screen_page_views, '
            .'organic_sessions, organic_total_users, organic_new_users; '
            .'опционально: engaged_sessions, engagement_rate, bounce_rate, '
            ."average_session_duration, event_count, organic_engaged_sessions.\n"
            ."```json\n"
            .$this->encodeJson($analytics)
            ."\n```";
    }

    /**
     * @param  list<array<string, mixed>>  $searchConsole
     * @param  array{
     *     queries: list<array<string, mixed>>,
     *     pages: list<array<string, mixed>>,
     *     devices: list<array<string, mixed>>,
     *     countries: list<array<string, mixed>>,
     *     search_appearances?: list<array<string, mixed>>
     * }  $dimensions
     */
    private function searchConsoleSection(array $searchConsole, array $dimensions): string
    {
        $hasDaily = $searchConsole !== [];
        $hasDimensions = ($dimensions['queries'] ?? []) !== []
            || ($dimensions['pages'] ?? []) !== []
            || ($dimensions['devices'] ?? []) !== []
            || ($dimensions['countries'] ?? []) !== []
            || ($dimensions['search_appearances'] ?? []) !== []
            || ($dimensions['sitemaps'] ?? []) !== []
            || ($dimensions['url_inspections'] ?? []) !== [];

        if (! $hasDaily && ! $hasDimensions) {
            return "## Google Search Console\nДанные за период отсутствуют.";
        }

        $parts = ['## Google Search Console'];

        if ($hasDaily) {
            $parts[] = "### Ежедневные метрики сайта\n"
                ."Поля: date, clicks, impressions, ctr, position.\n"
                ."```json\n"
                .$this->encodeJson($searchConsole)
                ."\n```";
        }

        $parts[] = $this->dimensionBlock(
            'Топ-запросы',
            SearchConsoleDimension::Query,
            $dimensions['queries'] ?? [],
            'value (текст запроса), rank, clicks, impressions, ctr, position',
        );
        $parts[] = $this->dimensionBlock(
            'Топ-страницы',
            SearchConsoleDimension::Page,
            $dimensions['pages'] ?? [],
            'value (URL), rank, clicks, impressions, ctr, position',
        );
        $parts[] = $this->dimensionBlock(
            'Устройства',
            SearchConsoleDimension::Device,
            $dimensions['devices'] ?? [],
            'value (DESKTOP/MOBILE/TABLET), rank, clicks, impressions, ctr, position',
        );
        $parts[] = $this->dimensionBlock(
            'Страны',
            SearchConsoleDimension::Country,
            $dimensions['countries'] ?? [],
            'value (код страны ISO 3166-1 alpha-3), rank, clicks, impressions, ctr, position',
        );
        $parts[] = $this->dimensionBlock(
            'Типы отображения в поиске',
            SearchConsoleDimension::SearchAppearance,
            $dimensions['search_appearances'] ?? [],
            'value (тип rich result / appearance), rank, clicks, impressions, ctr, position',
        );

        if (($dimensions['sitemaps'] ?? []) !== []) {
            $parts[] = "### Sitemaps\n"
                .'Поля: path, type, is_pending, is_sitemaps_index, last_downloaded_at, '
                ."last_submitted_at, errors, warnings, contents.\n"
                ."```json\n"
                .$this->encodeJson($dimensions['sitemaps'])
                ."\n```";
        }

        if (($dimensions['url_inspections'] ?? []) !== []) {
            $parts[] = "### URL Inspection\n"
                .'Поля: inspected_url, verdict, coverage_state, indexing_state, page_fetch_state, '
                .'robots_txt_state, crawled_as, last_crawl_time, google_canonical, user_canonical, '
                ."referring_urls, sitemaps, inspected_at.\n"
                ."```json\n"
                .$this->encodeJson($dimensions['url_inspections'])
                ."\n```";
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  array{
     *     lab?: list<array<string, mixed>>,
     *     crux?: list<array<string, mixed>>
     * }  $pageSpeed
     */
    private function pageSpeedSection(array $pageSpeed): string
    {
        $lab = $pageSpeed['lab'] ?? [];
        $crux = $pageSpeed['crux'] ?? [];

        if ($lab === [] && $crux === []) {
            return "## PageSpeed Insights / CrUX\nДанные отсутствуют.";
        }

        $parts = ['## PageSpeed Insights / CrUX'];

        if ($lab !== []) {
            $parts[] = "### Lab (Lighthouse)\n"
                .'Поля: url, strategy, fetched_at, performance_score, lcp_ms, inp_ms, cls, '
                ."fcp_ms, ttfb_ms, tbt_ms, speed_index_ms.\n"
                ."```json\n"
                .$this->encodeJson($lab)
                ."\n```";
        } else {
            $parts[] = "### Lab (Lighthouse)\nДанные отсутствуют.";
        }

        if ($crux !== []) {
            $parts[] = "### CrUX (field data)\n"
                .'Поля: scope (origin|url), url, form_factor, collection_period_start, '
                ."collection_period_end, lcp_p75_ms, inp_p75_ms, cls_p75, fcp_p75_ms, ttfb_p75_ms, fetched_at.\n"
                ."```json\n"
                .$this->encodeJson($crux)
                ."\n```";
        } else {
            $parts[] = "### CrUX (field data)\nДанные отсутствуют.";
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function dimensionBlock(string $title, SearchConsoleDimension $dimension, array $rows, string $fields): string
    {
        if ($rows === []) {
            return "### {$title}\nДанные за период отсутствуют.";
        }

        return "### {$title} ({$dimension->label()})\n"
            ."Поля: {$fields}.\n"
            ."```json\n"
            .$this->encodeJson($rows)
            ."\n```";
    }

    /**
     * @param  list<array<string, mixed>>  $commits
     */
    private function commitsSection(array $commits): string
    {
        if ($commits === []) {
            return "## Коммиты GitHub\nДанные за период отсутствуют.";
        }

        $total = count($commits);
        $included = array_slice($commits, 0, self::MAX_COMMITS_IN_PROMPT);
        $note = $total > self::MAX_COMMITS_IN_PROMPT
            ? "Показаны {$this->formatCount(count($included))} из {$this->formatCount($total)} коммитов (самые свежие).\n"
            : '';

        $simplified = array_map(function (array $row): array {
            return [
                'sha' => $row['short_sha'] ?? (isset($row['sha']) ? substr((string) $row['sha'], 0, 7) : null),
                'message' => $this->firstLine((string) ($row['message'] ?? '')),
                'author' => $row['author_name'] ?? null,
                'date' => $row['author_date'] ?? null,
            ];
        }, $included);

        return "## Коммиты GitHub\n"
            .$note
            ."Поля: sha, message, author, date.\n"
            ."```json\n"
            .$this->encodeJson($simplified)
            ."\n```";
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function eventsSection(array $events): string
    {
        if ($events === []) {
            return "## События\nСобытия за период не указаны.";
        }

        $total = count($events);
        $included = array_slice($events, 0, self::MAX_EVENTS_IN_PROMPT);
        $note = $total > self::MAX_EVENTS_IN_PROMPT
            ? "Показаны {$this->formatCount(count($included))} из {$this->formatCount($total)} событий (первые по дате).\n"
            : '';

        $simplified = array_map(function (array $row): array {
            return [
                'date' => $row['date'] ?? null,
                'title' => $row['title'] ?? null,
                'description' => $this->truncate((string) ($row['description'] ?? ''), 500),
                'url' => $row['url'] ?? null,
            ];
        }, $included);

        return "## События (ручные заметки за период)\n"
            .$note
            ."Поля: date, title, description, url.\n"
            ."```json\n"
            .$this->encodeJson($simplified)
            ."\n```";
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function encodeJson(array $rows): string
    {
        return (string) json_encode(
            $rows,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    private function firstLine(string $message): string
    {
        $line = strtok($message, "\n") ?: '';

        return $this->truncate($line, 200);
    }

    private function truncate(string $value, int $max): string
    {
        if ($value === '') {
            return $value;
        }

        return mb_strlen($value) > $max
            ? mb_substr($value, 0, $max - 3).'...'
            : $value;
    }

    private function formatCount(int $count): string
    {
        return number_format($count, 0, '.', ' ');
    }
}
