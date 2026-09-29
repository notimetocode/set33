<?php

namespace App\Services\PageSpeed;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PageSpeedApiClient
{
    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            'performance',
            'accessibility',
            'best-practices',
            'seo',
        ];
    }

    /**
     * @return array{
     *     url: string,
     *     strategy: string,
     *     performance_score: ?int,
     *     accessibility_score: ?int,
     *     best_practices_score: ?int,
     *     seo_score: ?int,
     *     lcp_ms: ?int,
     *     inp_ms: ?int,
     *     cls: ?float,
     *     fcp_ms: ?int,
     *     ttfb_ms: ?int,
     *     tbt_ms: ?int,
     *     speed_index_ms: ?int,
     *     payload: array<string, mixed>,
     *     crux_origin: ?array{
     *         scope: string,
     *         url: string,
     *         form_factor: string,
     *         overall_category: ?string,
     *         collection_period_start: ?string,
     *         collection_period_end: ?string,
     *         lcp_p75_ms: ?int,
     *         inp_p75_ms: ?int,
     *         cls_p75: ?float,
     *         fcp_p75_ms: ?int,
     *         ttfb_p75_ms: ?int,
     *         metrics: ?array<string, mixed>
     *     },
     *     crux_url: ?array{
     *         scope: string,
     *         url: string,
     *         form_factor: string,
     *         overall_category: ?string,
     *         collection_period_start: ?string,
     *         collection_period_end: ?string,
     *         lcp_p75_ms: ?int,
     *         inp_p75_ms: ?int,
     *         cls_p75: ?float,
     *         fcp_p75_ms: ?int,
     *         ttfb_p75_ms: ?int,
     *         metrics: ?array<string, mixed>
     *     }
     * }
     */
    public function runAudit(string $url, string $strategy): array
    {
        $baseUrl = rtrim((string) config('services.pagespeed.psi_base_url'), '/');
        $apiKey = trim((string) config('services.pagespeed.api_key', ''));

        $query = [
            'url' => $url,
            'strategy' => $strategy,
        ];

        if ($apiKey !== '') {
            $query['key'] = $apiKey;
        }

        $queryString = http_build_query($query);

        foreach (self::categories() as $category) {
            $queryString .= '&category='.rawurlencode($category);
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(90)
                ->get('/pagespeedonline/v5/runPagespeed?'.$queryString);
        } catch (ConnectionException) {
            throw new RuntimeException('Не удалось подключиться к PageSpeed Insights API.');
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage(
                $response->json(),
                'PageSpeed Insights API вернул ошибку.',
                $apiKey === '',
            ));
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $lighthouse = is_array($json['lighthouseResult'] ?? null) ? $json['lighthouseResult'] : [];
        $audits = is_array($lighthouse['audits'] ?? null) ? $lighthouse['audits'] : [];
        $categories = is_array($lighthouse['categories'] ?? null) ? $lighthouse['categories'] : [];

        $formFactor = $strategy === 'desktop' ? 'DESKTOP' : 'PHONE';

        return [
            'url' => mb_substr((string) ($json['id'] ?? $url), 0, 768),
            'strategy' => $strategy,
            'performance_score' => $this->categoryScore($categories, 'performance'),
            'accessibility_score' => $this->categoryScore($categories, 'accessibility'),
            'best_practices_score' => $this->categoryScore($categories, 'best-practices'),
            'seo_score' => $this->categoryScore($categories, 'seo'),
            'lcp_ms' => $this->auditMs($audits, 'largest-contentful-paint'),
            'inp_ms' => $this->auditMs($audits, 'interaction-to-next-paint')
                ?? $this->auditMs($audits, 'experimental-interaction-to-next-paint'),
            'cls' => $this->auditNumeric($audits, 'cumulative-layout-shift'),
            'fcp_ms' => $this->auditMs($audits, 'first-contentful-paint'),
            'ttfb_ms' => $this->auditMs($audits, 'server-response-time'),
            'tbt_ms' => $this->auditMs($audits, 'total-blocking-time'),
            'speed_index_ms' => $this->auditMs($audits, 'speed-index'),
            'payload' => $json,
            'crux_origin' => $this->parseLoadingExperience(
                is_array($json['originLoadingExperience'] ?? null) ? $json['originLoadingExperience'] : null,
                'origin',
                $formFactor,
            ),
            'crux_url' => $this->parseLoadingExperience(
                is_array($json['loadingExperience'] ?? null) ? $json['loadingExperience'] : null,
                'url',
                $formFactor,
            ),
        ];
    }

    public function normalizeOrigin(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Некорректный URL сайта для Chrome UX Report origin.');
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']);
    }

    /**
     * Resolve configured page URLs against the site base URL.
     *
     * @param  list<string>|null  $pageUrls
     * @return list<string>
     */
    public function resolvePageUrls(?array $pageUrls, string $siteUrl): array
    {
        $siteUrl = trim($siteUrl);

        if ($siteUrl === '') {
            throw new RuntimeException('У сайта не указан URL.');
        }

        $candidates = is_array($pageUrls) ? $pageUrls : [];
        $resolved = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = $this->normalizePageUrl(trim($candidate), $siteUrl);

            if ($normalized === null || in_array($normalized, $resolved, true)) {
                continue;
            }

            $resolved[] = $normalized;

            if (count($resolved) >= 10) {
                break;
            }
        }

        if ($resolved === []) {
            $fallback = $this->normalizePageUrl($siteUrl, $siteUrl);

            return $fallback === null ? [$siteUrl] : [$fallback];
        }

        return $resolved;
    }

    public function normalizePageUrl(string $url, string $siteUrl): ?string
    {
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($siteUrl, PHP_URL_SCHEME) ?: 'https';
            $url = $scheme.':'.$url;
        } elseif (str_starts_with($url, '/')) {
            $origin = $this->normalizeOrigin($siteUrl);
            $url = $origin.$url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return mb_substr($url, 0, 768);
    }

    /**
     * @param  array<string, mixed>  $categories
     */
    private function categoryScore(array $categories, string $id): ?int
    {
        $category = is_array($categories[$id] ?? null) ? $categories[$id] : null;

        if ($category === null || ! isset($category['score']) || ! is_numeric($category['score'])) {
            return null;
        }

        return (int) round(((float) $category['score']) * 100);
    }

    /**
     * @param  array<string, mixed>|null  $experience
     * @return array{
     *     scope: string,
     *     url: string,
     *     form_factor: string,
     *     overall_category: ?string,
     *     collection_period_start: ?string,
     *     collection_period_end: ?string,
     *     lcp_p75_ms: ?int,
     *     inp_p75_ms: ?int,
     *     cls_p75: ?float,
     *     fcp_p75_ms: ?int,
     *     ttfb_p75_ms: ?int,
     *     metrics: ?array<string, mixed>
     * }|null
     */
    private function parseLoadingExperience(?array $experience, string $scope, string $formFactor): ?array
    {
        if ($experience === null) {
            return null;
        }

        $metrics = is_array($experience['metrics'] ?? null) ? $experience['metrics'] : [];

        if ($metrics === []) {
            return null;
        }

        [$periodStart, $periodEnd] = $this->parseCollectionPeriod($experience);

        $overallCategory = $experience['overall_category'] ?? null;

        return [
            'scope' => $scope,
            'url' => mb_substr((string) ($experience['id'] ?? $experience['initial_url'] ?? ''), 0, 768),
            'form_factor' => $formFactor,
            'overall_category' => is_string($overallCategory) && $overallCategory !== ''
                ? mb_substr($overallCategory, 0, 32)
                : null,
            'collection_period_start' => $periodStart,
            'collection_period_end' => $periodEnd,
            'lcp_p75_ms' => $this->fieldPercentileMs($metrics, 'LARGEST_CONTENTFUL_PAINT_MS'),
            'inp_p75_ms' => $this->fieldPercentileMs($metrics, 'INTERACTION_TO_NEXT_PAINT')
                ?? $this->fieldPercentileMs($metrics, 'EXPERIMENTAL_INTERACTION_TO_NEXT_PAINT'),
            'cls_p75' => $this->fieldPercentileFloat($metrics, 'CUMULATIVE_LAYOUT_SHIFT_SCORE'),
            'fcp_p75_ms' => $this->fieldPercentileMs($metrics, 'FIRST_CONTENTFUL_PAINT_MS'),
            'ttfb_p75_ms' => $this->fieldPercentileMs($metrics, 'EXPERIMENTAL_TIME_TO_FIRST_BYTE'),
            'metrics' => $metrics,
        ];
    }

    /**
     * @param  array<string, mixed>  $experience
     * @return array{0: ?string, 1: ?string}
     */
    private function parseCollectionPeriod(array $experience): array
    {
        $period = is_array($experience['collectionPeriod'] ?? null)
            ? $experience['collectionPeriod']
            : (is_array($experience['collection_period'] ?? null) ? $experience['collection_period'] : null);

        if ($period === null) {
            return [null, null];
        }

        $start = $this->parsePeriodDate($period['firstDate'] ?? $period['startDate'] ?? $period['start'] ?? null);
        $end = $this->parsePeriodDate($period['lastDate'] ?? $period['endDate'] ?? $period['end'] ?? null);

        return [$start, $end];
    }

    private function parsePeriodDate(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        if (! is_array($value)) {
            return null;
        }

        $year = $value['year'] ?? null;
        $month = $value['month'] ?? null;
        $day = $value['day'] ?? null;

        if (! is_numeric($year) || ! is_numeric($month) || ! is_numeric($day)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
    }

    /**
     * @param  array<string, mixed>  $audits
     */
    private function auditMs(array $audits, string $id): ?int
    {
        $value = $this->auditNumeric($audits, $id);

        return $value === null ? null : (int) round($value);
    }

    /**
     * @param  array<string, mixed>  $audits
     */
    private function auditNumeric(array $audits, string $id): ?float
    {
        $audit = is_array($audits[$id] ?? null) ? $audits[$id] : null;

        if ($audit === null || ! isset($audit['numericValue']) || ! is_numeric($audit['numericValue'])) {
            return null;
        }

        return (float) $audit['numericValue'];
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function fieldPercentileMs(array $metrics, string $key): ?int
    {
        $value = $this->fieldPercentileFloat($metrics, $key);

        return $value === null ? null : (int) round($value);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function fieldPercentileFloat(array $metrics, string $key): ?float
    {
        $metric = is_array($metrics[$key] ?? null) ? $metrics[$key] : null;

        if ($metric === null || ! isset($metric['percentile']) || ! is_numeric($metric['percentile'])) {
            return null;
        }

        return (float) $metric['percentile'];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function errorMessage(?array $json, string $fallback, bool $missingApiKey = false): string
    {
        $message = $json['error']['message'] ?? null;

        if (is_string($message) && $message !== '') {
            $normalized = mb_strtolower($message);

            if (str_contains($normalized, 'quota') || str_contains($normalized, 'rate limit')) {
                if ($missingApiKey) {
                    return 'Превышена дневная квота PageSpeed Insights. Укажите PAGESPEED_API_KEY в .env (ключ Google Cloud с включённым PageSpeed Insights API).';
                }

                return 'Превышена квота PageSpeed Insights API. Попробуйте позже или увеличьте лимит в Google Cloud Console.';
            }

            return mb_substr($message, 0, 500);
        }

        return $fallback;
    }
}
