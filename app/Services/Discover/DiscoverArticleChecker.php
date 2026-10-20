<?php

namespace App\Services\Discover;

use App\Services\PageSpeed\PageSpeedApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class DiscoverArticleChecker
{
    private const USER_AGENT = 'Set33SiteBot/1.0 (+https://set33.com)';

    private const MAX_HTML_BYTES = 1_500_000;

    private const MAX_ROBOTS_BYTES = 512_000;

    private const MAX_SITEMAP_BYTES = 512_000;

    private const MAX_SITEMAP_FETCHES = 25;

    private const MAX_IMAGE_BYTES = 8_000_000;

    private const LCP_LIMIT_MS = 2500;

    private const INP_LIMIT_MS = 200;

    private const CLS_LIMIT = 0.1;

    public function __construct(
        private DiscoverHtmlParser $parser,
        private PageSpeedApiClient $pageSpeed,
    ) {}

    /**
     * @param  list<string>  $skip
     * @return array{
     *     url: string,
     *     final_url: string,
     *     checked_at: string,
     *     passed: bool,
     *     documentation_url: string,
     *     summary: array{pass: int, fail: int, warning: int, error: int, skipped: int},
     *     checks: list<array{id: string, status: string, message: string, details: array<string, mixed>}>
     * }
     */
    public function check(string $url, array $skip = []): array
    {
        $skip = array_values(array_unique($skip));
        $page = $this->fetchPage($url);
        $parsed = $page['fetched'] && $page['body'] !== ''
            ? $this->parser->parse($page['body'], $page['final_url'])
            : null;

        $checks = [];

        foreach (DiscoverCheckCatalog::ids() as $id) {
            if (in_array($id, $skip, true)) {
                $checks[] = $this->result($id, 'skipped', __('public.discover_check.skipped'));

                continue;
            }

            $checks[] = match ($id) {
                DiscoverCheckCatalog::AVAILABILITY => $this->checkAvailability($page),
                DiscoverCheckCatalog::INDEXING => $this->checkIndexing($page, $parsed),
                DiscoverCheckCatalog::MAX_IMAGE_PREVIEW => $this->checkMaxImagePreview($page, $parsed),
                DiscoverCheckCatalog::CANONICAL => $this->checkCanonical($page, $parsed),
                DiscoverCheckCatalog::AMP => $this->checkAmp($page, $parsed),
                DiscoverCheckCatalog::SITEMAPS => $this->checkSitemaps($page),
                DiscoverCheckCatalog::CORE_WEB_VITALS => $this->checkVitals($page),
                DiscoverCheckCatalog::COVER_IMAGE => $this->checkCover($page, $parsed),
                DiscoverCheckCatalog::AUTHOR => $this->checkAuthor($page, $parsed),
                DiscoverCheckCatalog::DATES => $this->checkDates($page, $parsed),
                DiscoverCheckCatalog::NEWS_ARTICLE => $this->checkNewsArticle($page, $parsed),
                DiscoverCheckCatalog::OPEN_GRAPH => $this->checkOpenGraph($page, $parsed),
            };
        }

        $summary = ['pass' => 0, 'fail' => 0, 'warning' => 0, 'error' => 0, 'skipped' => 0];

        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return [
            'url' => $url,
            'final_url' => $page['final_url'],
            'checked_at' => now()->toIso8601String(),
            'passed' => $summary['fail'] === 0 && $summary['error'] === 0,
            'documentation_url' => url('/docs/discover'),
            'summary' => $summary,
            'checks' => $checks,
        ];
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkAvailability(array $page): array
    {
        if (! $page['fetched']) {
            return $this->result(
                DiscoverCheckCatalog::AVAILABILITY,
                'error',
                __('public.discover_check.availability_error'),
                ['error' => $page['error']],
            );
        }

        $status = (int) $page['http_status'];

        if ($status >= 200 && $status < 300) {
            return $this->result(
                DiscoverCheckCatalog::AVAILABILITY,
                'pass',
                __('public.discover_check.availability_pass', ['status' => $status]),
                ['http_status' => $status],
            );
        }

        return $this->result(
            DiscoverCheckCatalog::AVAILABILITY,
            'fail',
            __('public.discover_check.availability_fail', ['status' => $status]),
            ['http_status' => $status],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkIndexing(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::INDEXING);
        }

        $reasons = [];

        if ($this->blocksIndexing((string) ($parsed['robots'] ?? ''))) {
            $reasons[] = 'meta';
        }

        if ($this->blocksIndexing($page['robots_header'])) {
            $reasons[] = 'header';
        }

        $robots = $this->fetchRobots($page['final_url']);
        $path = parse_url($page['final_url'], PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        if ($robots !== null && ! $this->robotsAllowsPath($robots, $path)) {
            $reasons[] = 'robots';
        }

        if ($reasons !== []) {
            return $this->result(
                DiscoverCheckCatalog::INDEXING,
                'fail',
                __('public.discover_check.indexing_fail'),
                ['reasons' => $reasons, 'meta_robots' => $parsed['robots'], 'x_robots_tag' => $page['robots_header'] !== '' ? $page['robots_header'] : null],
            );
        }

        return $this->result(
            DiscoverCheckCatalog::INDEXING,
            'pass',
            __('public.discover_check.indexing_pass'),
            ['meta_robots' => $parsed['robots']],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkMaxImagePreview(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::MAX_IMAGE_PREVIEW);
        }

        $robots = strtolower((string) preg_replace('/\s*:\s*/', ':', (string) ($parsed['robots'] ?? '')));
        $allowed = str_contains($robots, 'max-image-preview:large');

        return $this->result(
            DiscoverCheckCatalog::MAX_IMAGE_PREVIEW,
            $allowed ? 'pass' : 'fail',
            __($allowed ? 'public.discover_check.max_image_preview_pass' : 'public.discover_check.max_image_preview_fail'),
            ['meta_robots' => $parsed['robots']],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkCanonical(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::CANONICAL);
        }

        $canonical = $parsed['canonical'];

        if (! is_string($canonical) || $canonical === '' || $parsed['canonical_absolute'] !== true) {
            return $this->result(
                DiscoverCheckCatalog::CANONICAL,
                'fail',
                __('public.discover_check.canonical_fail_missing'),
                ['canonical' => $canonical],
            );
        }

        if (! $this->urlsMatch($canonical, $page['final_url'])) {
            return $this->result(
                DiscoverCheckCatalog::CANONICAL,
                'fail',
                __('public.discover_check.canonical_fail_mismatch'),
                ['canonical' => $canonical, 'final_url' => $page['final_url']],
            );
        }

        return $this->result(
            DiscoverCheckCatalog::CANONICAL,
            'pass',
            __('public.discover_check.canonical_pass'),
            ['canonical' => $canonical],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkAmp(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::AMP);
        }

        $ampUrl = $parsed['amphtml'];

        if (! is_string($ampUrl) || $ampUrl === '') {
            return $this->result(
                DiscoverCheckCatalog::AMP,
                'pass',
                __('public.discover_check.amp_pass_absent'),
                ['present' => false],
            );
        }

        if (PublicUrlGuard::isBlockedUrl($ampUrl)) {
            return $this->result(
                DiscoverCheckCatalog::AMP,
                'fail',
                __('public.discover_check.amp_fail'),
                ['present' => true, 'amp_url' => $ampUrl, 'reason' => 'blocked'],
            );
        }

        $response = $this->get($ampUrl);

        if ($response === null || $response->status() !== 200) {
            return $this->result(
                DiscoverCheckCatalog::AMP,
                'fail',
                __('public.discover_check.amp_fail'),
                ['present' => true, 'amp_url' => $ampUrl, 'http_status' => $response?->status()],
            );
        }

        $ampParsed = $this->parser->parse($response->body(), (string) ($response->effectiveUri() ?? $ampUrl));
        $canonical = $ampParsed['canonical'];
        $matches = $ampParsed['canonical_absolute'] === true && $this->urlsMatch($canonical, $page['final_url']);

        return $this->result(
            DiscoverCheckCatalog::AMP,
            $matches ? 'pass' : 'fail',
            __($matches ? 'public.discover_check.amp_pass' : 'public.discover_check.amp_fail'),
            ['present' => true, 'amp_url' => $ampUrl, 'canonical' => $canonical],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkSitemaps(array $page): array
    {
        $origin = $this->parser->origin($page['final_url']);
        $paths = ['/sitemap.xml', '/xml-google-1/', '/news-sitemap.xml'];
        $cache = [];
        $remaining = self::MAX_SITEMAP_FETCHES;
        $rows = [];
        $missing = [];

        foreach ($paths as $path) {
            $entryUrl = $origin === null ? null : $origin.$path;
            $found = false;
            $status = null;

            if ($entryUrl !== null) {
                $visiting = [];
                $found = $this->sitemapContains($entryUrl, $page['final_url'], $cache, $remaining, $visiting);
                $status = $cache[$entryUrl]['status'] ?? null;
            }

            if (! $found) {
                $missing[] = $path;
            }

            $rows[] = [
                'path' => $path,
                'url' => $entryUrl,
                'found' => $found,
                'http_status' => $status,
            ];
        }

        $passed = $missing === [];

        return $this->result(
            DiscoverCheckCatalog::SITEMAPS,
            $passed ? 'pass' : 'fail',
            __($passed ? 'public.discover_check.sitemaps_pass' : 'public.discover_check.sitemaps_fail'),
            ['sitemaps' => $rows, 'missing' => $missing],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkVitals(array $page): array
    {
        try {
            $audit = $this->pageSpeed->runMobilePerformance($page['final_url']);
        } catch (Throwable $exception) {
            return $this->result(
                DiscoverCheckCatalog::CORE_WEB_VITALS,
                'error',
                __('public.discover_check.vitals_error'),
                ['error' => $exception->getMessage()],
            );
        }

        $crux = is_array($audit['crux_url'] ?? null) ? $audit['crux_url'] : null;
        $lcp = $this->metric($crux['lcp_p75_ms'] ?? null, $audit['lcp_ms'] ?? null);
        $inp = $this->metric($crux['inp_p75_ms'] ?? null, $audit['inp_ms'] ?? null);
        $cls = $this->metric($crux['cls_p75'] ?? null, $audit['cls'] ?? null);

        $failures = [];

        if ($lcp['value'] !== null && (float) $lcp['value'] > self::LCP_LIMIT_MS) {
            $failures[] = 'lcp';
        }

        if ($inp['value'] !== null && (float) $inp['value'] > self::INP_LIMIT_MS) {
            $failures[] = 'inp';
        }

        if ($cls['value'] !== null && (float) $cls['value'] > self::CLS_LIMIT) {
            $failures[] = 'cls';
        }

        $missing = [];

        foreach (['lcp' => $lcp, 'inp' => $inp, 'cls' => $cls] as $name => $metric) {
            if ($metric['value'] === null) {
                $missing[] = $name;
            }
        }

        $status = 'pass';
        $message = __('public.discover_check.vitals_pass');

        if ($failures !== []) {
            $status = 'fail';
            $message = __('public.discover_check.vitals_fail');
        } elseif ($missing !== []) {
            $status = 'warning';
            $message = __('public.discover_check.vitals_warning');
        }

        return $this->result(DiscoverCheckCatalog::CORE_WEB_VITALS, $status, $message, [
            'lcp_ms' => $lcp['value'],
            'inp_ms' => $inp['value'],
            'cls' => $cls['value'],
            'lcp_source' => $lcp['source'],
            'inp_source' => $inp['source'],
            'cls_source' => $cls['source'],
            'failures' => $failures,
            'missing' => $missing,
            'thresholds' => [
                'lcp_ms' => self::LCP_LIMIT_MS,
                'inp_ms' => self::INP_LIMIT_MS,
                'cls' => self::CLS_LIMIT,
            ],
        ]);
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkCover(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::COVER_IMAGE);
        }

        $imageUrl = is_string($parsed['og_image'] ?? null) ? $parsed['og_image'] : null;

        if ($imageUrl === null) {
            foreach ($parsed['news_articles'] as $article) {
                if (is_string($article['image'] ?? null) && $article['image'] !== '') {
                    $imageUrl = $article['image'];
                    break;
                }
            }
        }

        if ($imageUrl === null) {
            return $this->result(
                DiscoverCheckCatalog::COVER_IMAGE,
                'fail',
                __('public.discover_check.cover_fail_missing'),
            );
        }

        if (PublicUrlGuard::isBlockedUrl($imageUrl)) {
            return $this->result(
                DiscoverCheckCatalog::COVER_IMAGE,
                'fail',
                __('public.discover_check.cover_fail_blocked'),
                ['image_url' => $imageUrl],
            );
        }

        $response = $this->get($imageUrl);

        if ($response === null || ! $response->successful()) {
            return $this->result(
                DiscoverCheckCatalog::COVER_IMAGE,
                'fail',
                __('public.discover_check.cover_fail_fetch'),
                ['image_url' => $imageUrl, 'http_status' => $response?->status()],
            );
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_IMAGE_BYTES) {
            return $this->result(
                DiscoverCheckCatalog::COVER_IMAGE,
                'fail',
                __('public.discover_check.cover_fail_dimensions'),
                ['image_url' => $imageUrl],
            );
        }

        $info = @getimagesizefromstring($body);

        if ($info === false || ! isset($info[0], $info[1]) || (int) $info[1] < 1) {
            return $this->result(
                DiscoverCheckCatalog::COVER_IMAGE,
                'fail',
                __('public.discover_check.cover_fail_dimensions'),
                ['image_url' => $imageUrl],
            );
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $ratio = $width / $height;
        $ratioOk = $ratio >= 1.7 && $ratio <= 1.85;
        $wideEnough = $width >= 1200;

        $status = 'pass';
        $message = __('public.discover_check.cover_pass');

        if (! $wideEnough) {
            $status = 'fail';
            $message = __('public.discover_check.cover_fail_width');
        } elseif (! $ratioOk) {
            $status = 'warning';
            $message = __('public.discover_check.cover_warning_ratio');
        }

        return $this->result(DiscoverCheckCatalog::COVER_IMAGE, $status, $message, [
            'image_url' => $imageUrl,
            'width' => $width,
            'height' => $height,
            'ratio' => round($ratio, 3),
        ]);
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkAuthor(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::AUTHOR);
        }

        $candidates = $parsed['author_links'];

        foreach ($parsed['news_articles'] as $article) {
            foreach ($article['author_urls'] as $authorUrl) {
                $candidates[] = $authorUrl;
            }
        }

        foreach ($parsed['avtor_links'] as $authorUrl) {
            $candidates[] = $authorUrl;
        }

        $candidates = array_values(array_unique($candidates));
        $restricted = $this->requiresAuthorPath($page['final_url']);

        if ($restricted) {
            $candidates = array_values(array_filter(
                $candidates,
                fn (string $candidate): bool => $this->isAuthorPath($candidate),
            ));
        }

        $candidates = array_slice($candidates, 0, 5);

        if ($candidates === []) {
            return $this->result(
                DiscoverCheckCatalog::AUTHOR,
                'fail',
                __($restricted ? 'public.discover_check.author_fail_pattern' : 'public.discover_check.author_fail_missing'),
            );
        }

        $attempts = [];

        foreach ($candidates as $candidate) {
            if (PublicUrlGuard::isBlockedUrl($candidate)) {
                $attempts[] = ['url' => $candidate, 'http_status' => null, 'blocked' => true];

                continue;
            }

            $response = $this->get($candidate);
            $status = $response?->status();
            $attempts[] = ['url' => $candidate, 'http_status' => $status, 'blocked' => false];

            if ($status === 200) {
                return $this->result(
                    DiscoverCheckCatalog::AUTHOR,
                    'pass',
                    __('public.discover_check.author_pass'),
                    ['url' => $candidate, 'attempts' => $attempts],
                );
            }
        }

        return $this->result(
            DiscoverCheckCatalog::AUTHOR,
            'fail',
            __('public.discover_check.author_fail_status'),
            ['attempts' => $attempts],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkDates(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::DATES);
        }

        $publishedRaw = is_string($parsed['published_time'] ?? null) ? $parsed['published_time'] : null;
        $modifiedRaw = is_string($parsed['modified_time'] ?? null) ? $parsed['modified_time'] : null;
        $published = $this->parseTime($publishedRaw);

        if ($published === null) {
            return $this->result(
                DiscoverCheckCatalog::DATES,
                'fail',
                __('public.discover_check.dates_fail_published'),
                ['published_time' => $publishedRaw, 'modified_time' => $modifiedRaw],
            );
        }

        if ($modifiedRaw === null) {
            return $this->result(
                DiscoverCheckCatalog::DATES,
                'warning',
                __('public.discover_check.dates_warning_modified'),
                ['published_time' => $published, 'modified_time' => null],
            );
        }

        $modified = $this->parseTime($modifiedRaw);

        if ($modified === null) {
            return $this->result(
                DiscoverCheckCatalog::DATES,
                'fail',
                __('public.discover_check.dates_fail_modified'),
                ['published_time' => $published, 'modified_time' => $modifiedRaw],
            );
        }

        return $this->result(
            DiscoverCheckCatalog::DATES,
            'pass',
            __('public.discover_check.dates_pass'),
            ['published_time' => $published, 'modified_time' => $modified],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkNewsArticle(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::NEWS_ARTICLE);
        }

        $articles = $parsed['news_articles'];

        if ($articles === []) {
            return $this->result(
                DiscoverCheckCatalog::NEWS_ARTICLE,
                'fail',
                __('public.discover_check.news_fail_missing'),
            );
        }

        $gaps = [];

        foreach ($articles as $article) {
            $gaps = $this->newsGaps($article);

            if ($gaps === []) {
                return $this->result(
                    DiscoverCheckCatalog::NEWS_ARTICLE,
                    'pass',
                    __('public.discover_check.news_pass'),
                    ['headline' => $article['headline']],
                );
            }
        }

        return $this->result(
            DiscoverCheckCatalog::NEWS_ARTICLE,
            'fail',
            __('public.discover_check.news_fail_fields', ['fields' => implode(', ', $gaps)]),
            ['missing' => $gaps],
        );
    }

    /**
     * @param  array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}  $page
     * @param  array<string, mixed>|null  $parsed
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function checkOpenGraph(array $page, ?array $parsed): array
    {
        if ($parsed === null) {
            return $this->unreadable(DiscoverCheckCatalog::OPEN_GRAPH);
        }

        $problems = [];

        if (! is_string($parsed['og_title'] ?? null) || $parsed['og_title'] === '') {
            $problems[] = 'og:title';
        }

        if (! is_string($parsed['og_description'] ?? null) || $parsed['og_description'] === '') {
            $problems[] = 'og:description';
        }

        if ($parsed['og_image_absolute'] !== true) {
            $problems[] = 'og:image';
        }

        if (($parsed['og_image_width'] ?? null) !== '1200') {
            $problems[] = 'og:image:width';
        }

        if (($parsed['og_image_height'] ?? null) !== '675') {
            $problems[] = 'og:image:height';
        }

        if (($parsed['og_image_type'] ?? null) !== 'image/webp') {
            $problems[] = 'og:image:type';
        }

        if ($parsed['og_url_absolute'] !== true || ! $this->urlsMatch(
            is_string($parsed['og_url'] ?? null) ? $parsed['og_url'] : null,
            $page['final_url'],
        )) {
            $problems[] = 'og:url';
        }

        if ($problems !== []) {
            return $this->result(
                DiscoverCheckCatalog::OPEN_GRAPH,
                'fail',
                __('public.discover_check.og_fail', ['fields' => implode(', ', $problems)]),
                ['missing' => $problems],
            );
        }

        return $this->result(
            DiscoverCheckCatalog::OPEN_GRAPH,
            'pass',
            __('public.discover_check.og_pass'),
            [
                'og_title' => $parsed['og_title'],
                'og_url' => $parsed['og_url'],
                'og_image' => $parsed['og_image'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $article
     * @return list<string>
     */
    private function newsGaps(array $article): array
    {
        $gaps = [];

        if ($article['headline'] === null) {
            $gaps[] = 'headline';
        }

        if ($article['image'] === null) {
            $gaps[] = 'image';
        }

        if ($article['date_published'] === null) {
            $gaps[] = 'datePublished';
        }

        if ($article['date_modified'] === null) {
            $gaps[] = 'dateModified';
        }

        if ($article['author_is_person'] !== true) {
            $gaps[] = 'author';
        }

        if ($article['publisher_has_logo'] !== true) {
            $gaps[] = 'publisher';
        }

        return $gaps;
    }

    /**
     * @return array{value: int|float|null, source: ?string}
     */
    private function metric(int|float|null $field, int|float|null $lab): array
    {
        if ($field !== null) {
            return ['value' => $field, 'source' => 'crux'];
        }

        if ($lab !== null) {
            return ['value' => $lab, 'source' => 'lab'];
        }

        return ['value' => null, 'source' => null];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function result(string $id, string $status, string $message, array $details = []): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'message' => $message,
            'details' => $details,
        ];
    }

    /**
     * @return array{id: string, status: string, message: string, details: array<string, mixed>}
     */
    private function unreadable(string $id): array
    {
        return $this->result($id, 'error', __('public.discover_check.page_unreadable'));
    }

    /**
     * @return array{fetched: bool, http_status: ?int, final_url: string, body: string, robots_header: string, error: ?string}
     */
    private function fetchPage(string $url): array
    {
        try {
            $response = $this->client()
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                ])
                ->connectTimeout(5)
                ->timeout(12)
                ->retry([200, 500], 0, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429));
                }, throw: false)
                ->get($url);
        } catch (Throwable $exception) {
            return [
                'fetched' => false,
                'http_status' => null,
                'final_url' => $url,
                'body' => '',
                'robots_header' => '',
                'error' => $exception->getMessage(),
            ];
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_HTML_BYTES) {
            $body = substr($body, 0, self::MAX_HTML_BYTES);
        }

        return [
            'fetched' => true,
            'http_status' => $response->status(),
            'final_url' => (string) ($response->effectiveUri() ?? $url),
            'body' => $body,
            'robots_header' => (string) $response->header('X-Robots-Tag'),
            'error' => null,
        ];
    }

    private function fetchRobots(string $pageUrl): ?string
    {
        $origin = $this->parser->origin($pageUrl);

        if ($origin === null) {
            return null;
        }

        $response = $this->get($origin.'/robots.txt');

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_ROBOTS_BYTES) {
            return null;
        }

        return $body;
    }

    private function robotsAllowsPath(string $content, string $path): bool
    {
        $groups = [];
        $agents = [];
        $rules = [];

        $flush = function () use (&$groups, &$agents, &$rules): void {
            if ($agents === []) {
                return;
            }

            foreach ($agents as $agent) {
                $key = strtolower($agent);
                $groups[$key] = array_merge($groups[$key] ?? [], $rules);
            }

            $agents = [];
            $rules = [];
        };

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s+#.*$/', '', $line));

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $matches) === 1) {
                if ($rules !== []) {
                    $flush();
                }

                $agents[] = trim($matches[1]);

                continue;
            }

            if (preg_match('/^(allow|disallow):\s*(.*)$/i', $line, $matches) === 1) {
                if ($agents === []) {
                    continue;
                }

                $rules[] = [
                    'type' => strtolower($matches[1]),
                    'path' => trim($matches[2]),
                ];
            }
        }

        $flush();

        $selected = $groups['googlebot'] ?? $groups['*'] ?? [];
        $bestAllow = -1;
        $bestDisallow = -1;

        foreach ($selected as $rule) {
            if ($rule['path'] === '') {
                continue;
            }

            if (! $this->robotsRuleMatches($rule['path'], $path)) {
                continue;
            }

            $length = strlen($rule['path']);

            if ($rule['type'] === 'allow' && $length >= $bestAllow) {
                $bestAllow = $length;
            }

            if ($rule['type'] === 'disallow' && $length >= $bestDisallow) {
                $bestDisallow = $length;
            }
        }

        if ($bestDisallow < 0) {
            return true;
        }

        return $bestAllow >= $bestDisallow;
    }

    private function robotsRuleMatches(string $pattern, string $path): bool
    {
        $anchorEnd = str_ends_with($pattern, '$');
        $body = $anchorEnd ? substr($pattern, 0, -1) : $pattern;
        $regex = str_replace('\*', '.*', preg_quote($body, '#'));

        return preg_match('#^'.$regex.($anchorEnd ? '$' : '').'#', $path) === 1;
    }

    /**
     * @param  array<string, array{status: ?int, body: string, error: ?string}>  $cache
     * @param  array<string, bool>  $visiting
     */
    private function sitemapContains(string $entryUrl, string $articleUrl, array &$cache, int &$remaining, array &$visiting): bool
    {
        if (isset($visiting[$entryUrl])) {
            return false;
        }

        $visiting[$entryUrl] = true;
        $fetched = $this->fetchSitemap($entryUrl, $cache, $remaining);

        if ($fetched['error'] !== null || $fetched['body'] === '') {
            return false;
        }

        $locs = $this->sitemapLocs($fetched['body']);
        $isIndex = stripos($fetched['body'], '<sitemapindex') !== false;

        if ($isIndex) {
            foreach ($locs as $loc) {
                $child = $this->parser->absolutize($loc, $entryUrl) ?? $loc;

                if ($this->sitemapContains($child, $articleUrl, $cache, $remaining, $visiting)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($locs as $loc) {
            $absolute = $this->parser->absolutize($loc, $entryUrl) ?? $loc;

            if ($this->urlsMatch($absolute, $articleUrl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array{status: ?int, body: string, error: ?string}>  $cache
     * @return array{status: ?int, body: string, error: ?string}
     */
    private function fetchSitemap(string $url, array &$cache, int &$remaining): array
    {
        if (isset($cache[$url])) {
            return $cache[$url];
        }

        if ($remaining <= 0 || PublicUrlGuard::isBlockedUrl($url)) {
            return $cache[$url] = [
                'status' => null,
                'body' => '',
                'error' => $remaining <= 0 ? 'budget' : 'blocked',
            ];
        }

        $remaining--;
        $response = $this->get($url);

        if ($response === null) {
            return $cache[$url] = ['status' => null, 'body' => '', 'error' => 'fetch_failed'];
        }

        if (! $response->successful()) {
            return $cache[$url] = ['status' => $response->status(), 'body' => '', 'error' => 'http'];
        }

        return $cache[$url] = [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, self::MAX_SITEMAP_BYTES),
            'error' => null,
        ];
    }

    /**
     * @return list<string>
     */
    private function sitemapLocs(string $body): array
    {
        if (preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#i', $body, $matches) < 1) {
            return [];
        }

        return array_map(
            static fn (string $loc): string => trim(html_entity_decode($loc, ENT_QUOTES | ENT_HTML5)),
            $matches[1],
        );
    }

    private function blocksIndexing(string $content): bool
    {
        if ($content === '') {
            return false;
        }

        $tokens = preg_split('/\s*,\s*/', strtolower($content)) ?: [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if (in_array($token, ['noindex', 'nofollow', 'none'], true)) {
                return true;
            }
        }

        return false;
    }

    private function requiresAuthorPath(string $pageUrl): bool
    {
        $host = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));

        return in_array($host, ['digitalbusiness.kz', 'www.digitalbusiness.kz'], true);
    }

    private function isAuthorPath(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/avtor-teksta(?:-ru)?/#', $path) === 1;
    }

    private function parseTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function urlsMatch(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return $this->normalizeUrl($left) === $this->normalizeUrl($right);
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return strtolower(rtrim($url, '/'));
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $port = '';

        if (isset($parts['port'])) {
            $isDefault = ($scheme === 'https' && (int) $parts['port'] === 443)
                || ($scheme === 'http' && (int) $parts['port'] === 80);

            if (! $isDefault) {
                $port = ':'.$parts['port'];
            }
        }

        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : $path;

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => '*/*',
        ])->withOptions(['allow_redirects' => ['max' => 5]]);
    }

    private function get(string $url): ?Response
    {
        if (PublicUrlGuard::isBlockedUrl($url)) {
            return null;
        }

        try {
            return $this->client()
                ->connectTimeout(5)
                ->timeout(12)
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }
}
