<?php

namespace App\Services\PageSpeed;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PageSpeedApiClient
{
    /**
     * @return array{
     *     url: string,
     *     strategy: string,
     *     performance_score: ?int,
     *     lcp_ms: ?int,
     *     inp_ms: ?int,
     *     cls: ?float,
     *     fcp_ms: ?int,
     *     ttfb_ms: ?int,
     *     tbt_ms: ?int,
     *     speed_index_ms: ?int,
     *     crux_origin: ?array{
     *         scope: string,
     *         url: string,
     *         form_factor: string,
     *         collection_period_start: ?string,
     *         collection_period_end: ?string,
     *         lcp_p75_ms: ?int,
     *         inp_p75_ms: ?int,
     *         cls_p75: ?float,
     *         fcp_p75_ms: ?int,
     *         ttfb_p75_ms: ?int
     *     },
     *     crux_url: ?array{
     *         scope: string,
     *         url: string,
     *         form_factor: string,
     *         collection_period_start: ?string,
     *         collection_period_end: ?string,
     *         lcp_p75_ms: ?int,
     *         inp_p75_ms: ?int,
     *         cls_p75: ?float,
     *         fcp_p75_ms: ?int,
     *         ttfb_p75_ms: ?int
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
            'category' => 'performance',
        ];

        if ($apiKey !== '') {
            $query['key'] = $apiKey;
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(90)
                ->get('/pagespeedonline/v5/runPagespeed', $query);
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
        $performance = is_array($categories['performance'] ?? null) ? $categories['performance'] : [];

        $score = isset($performance['score']) && is_numeric($performance['score'])
            ? (int) round(((float) $performance['score']) * 100)
            : null;

        $formFactor = $strategy === 'desktop' ? 'DESKTOP' : 'PHONE';

        return [
            'url' => mb_substr((string) ($json['id'] ?? $url), 0, 768),
            'strategy' => $strategy,
            'performance_score' => $score,
            'lcp_ms' => $this->auditMs($audits, 'largest-contentful-paint'),
            'inp_ms' => $this->auditMs($audits, 'interaction-to-next-paint')
                ?? $this->auditMs($audits, 'experimental-interaction-to-next-paint'),
            'cls' => $this->auditNumeric($audits, 'cumulative-layout-shift'),
            'fcp_ms' => $this->auditMs($audits, 'first-contentful-paint'),
            'ttfb_ms' => $this->auditMs($audits, 'server-response-time'),
            'tbt_ms' => $this->auditMs($audits, 'total-blocking-time'),
            'speed_index_ms' => $this->auditMs($audits, 'speed-index'),
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
            throw new RuntimeException('Некорректный URL сайта для CrUX origin.');
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']);
    }

    /**
     * @param  array<string, mixed>|null  $experience
     * @return array{
     *     scope: string,
     *     url: string,
     *     form_factor: string,
     *     collection_period_start: ?string,
     *     collection_period_end: ?string,
     *     lcp_p75_ms: ?int,
     *     inp_p75_ms: ?int,
     *     cls_p75: ?float,
     *     fcp_p75_ms: ?int,
     *     ttfb_p75_ms: ?int
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

        return [
            'scope' => $scope,
            'url' => mb_substr((string) ($experience['id'] ?? $experience['initial_url'] ?? ''), 0, 768),
            'form_factor' => $formFactor,
            'collection_period_start' => null,
            'collection_period_end' => null,
            'lcp_p75_ms' => $this->fieldPercentileMs($metrics, 'LARGEST_CONTENTFUL_PAINT_MS'),
            'inp_p75_ms' => $this->fieldPercentileMs($metrics, 'INTERACTION_TO_NEXT_PAINT')
                ?? $this->fieldPercentileMs($metrics, 'EXPERIMENTAL_INTERACTION_TO_NEXT_PAINT'),
            'cls_p75' => $this->fieldPercentileFloat($metrics, 'CUMULATIVE_LAYOUT_SHIFT_SCORE'),
            'fcp_p75_ms' => $this->fieldPercentileMs($metrics, 'FIRST_CONTENTFUL_PAINT_MS'),
            'ttfb_p75_ms' => $this->fieldPercentileMs($metrics, 'EXPERIMENTAL_TIME_TO_FIRST_BYTE'),
        ];
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
