<?php

namespace App\Services\SiteAudit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WebsiteAnalyzer
{
    private const USER_AGENT = 'Set33SiteBot/1.0 (+https://set33.com)';

    private const MAX_HTML_BYTES = 1_500_000;

    private const MAX_ROBOTS_BYTES = 512_000;

    private const MAX_SITEMAP_BYTES = 512_000;

    public function __construct(
        private HtmlDocumentParser $parser = new HtmlDocumentParser,
        private PageContentAnalyzer $contentAnalyzer = new PageContentAnalyzer,
    ) {}

    public function analyze(string $url): WebsiteAuditReport
    {
        $requestedUrl = $this->normalizeRequestedUrl($url);

        if ($requestedUrl === null) {
            return WebsiteAuditReport::failed($url, 'Invalid URL.');
        }

        try {
            $homepage = $this->fetchHtml($requestedUrl, throwOnError: true);
        } catch (Throwable $exception) {
            $httpStatus = null;

            if ($exception instanceof RequestException && $exception->response !== null) {
                $httpStatus = $exception->response->status();
            }

            return WebsiteAuditReport::failed(
                $requestedUrl,
                Str::limit($exception->getMessage(), 2000),
                $httpStatus,
            );
        }

        $finalUrl = $homepage['final_url'];
        $meta = $this->parser->parse($homepage['body'], $finalUrl);
        $content = $this->contentAnalyzer->analyze($homepage['body'], $finalUrl);
        $robots = $this->fetchRobotsTxt($finalUrl);
        $sitemap = $this->resolveSitemap($finalUrl, $robots['content']);
        $wwwRedirect = $this->checkWwwRedirect($finalUrl);
        $notFoundPage = $this->checkNotFoundPage($finalUrl);
        $ssl = $this->checkSsl($finalUrl);
        $ipAddress = $this->resolveIpAddress($finalUrl);

        $requestedScheme = Str::lower((string) parse_url($requestedUrl, PHP_URL_SCHEME));
        $finalScheme = Str::lower((string) parse_url($finalUrl, PHP_URL_SCHEME));

        $ogTitlePresent = filled($meta['og_title']);
        $ogDescriptionPresent = filled($meta['og_description']);
        $ogImagePresent = filled($meta['og_image_url']);

        return new WebsiteAuditReport(
            requestedUrl: $requestedUrl,
            finalUrl: $finalUrl,
            fetchedAt: now(),
            status: 'ready',
            error: null,
            httpStatus: $homepage['http_status'],
            responseTimeMs: $homepage['response_time_ms'],
            redirected: ! $this->urlsEqual($requestedUrl, $finalUrl),
            ipAddress: $ipAddress,
            ssl: $ssl,
            httpsRedirect: $requestedScheme === 'http' && $finalScheme === 'https',
            wwwRedirect: $wwwRedirect,
            notFoundPage: $notFoundPage,
            metaRobots: $meta['meta_robots'],
            robotsTxt: [
                'present' => $robots['present'],
                'http_status' => $robots['http_status'],
                'content' => $robots['content'],
                'indexing_allowed' => $robots['indexing_allowed'],
            ],
            sitemap: $sitemap,
            title: $meta['title'],
            titlePresent: filled($meta['title']),
            metaDescription: $meta['meta_description'],
            metaDescriptionPresent: filled($meta['meta_description']),
            metaKeywords: $meta['meta_keywords'],
            htmlLang: $meta['html_lang'],
            charset: $meta['charset'],
            viewport: $meta['viewport'],
            viewportPresent: filled($meta['viewport']),
            canonicalUrl: $meta['canonical_url'],
            canonicalPresent: filled($meta['canonical_url']),
            ogTitle: $meta['og_title'],
            ogTitlePresent: $ogTitlePresent,
            ogDescription: $meta['og_description'],
            ogDescriptionPresent: $ogDescriptionPresent,
            ogImageUrl: $meta['og_image_url'],
            ogImagePresent: $ogImagePresent,
            openGraphPresent: $ogTitlePresent || $ogDescriptionPresent || $ogImagePresent,
            icons: $meta['icons'],
            googleAnalytics: $meta['google_analytics'],
            yandexMetrika: $meta['yandex_metrika'],
            content: $content,
        );
    }

    private function normalizeRequestedUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return $url;
    }

    /**
     * @return array{body: string, final_url: string, http_status: int, response_time_ms: int}
     */
    private function fetchHtml(string $url, bool $throwOnError = false): array
    {
        $startedAt = hrtime(true);

        $response = Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            'User-Agent' => self::USER_AGENT,
        ])
            ->withOptions(['allow_redirects' => ['max' => 5]])
            ->connectTimeout(5)
            ->timeout(12)
            ->retry([200, 500], 0, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429));
            }, throw: false)
            ->get($url);

        $responseTimeMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($throwOnError && ! $response->successful()) {
            $response->throw();
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_HTML_BYTES) {
            $body = substr($body, 0, self::MAX_HTML_BYTES);
        }

        return [
            'body' => $body,
            'final_url' => (string) ($response->effectiveUri() ?? $url),
            'http_status' => $response->status(),
            'response_time_ms' => max(0, $responseTimeMs),
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     valid: bool,
     *     inspected: bool,
     *     issuer: ?string,
     *     subject: ?string,
     *     valid_from: ?string,
     *     valid_to: ?string,
     *     days_remaining: ?int
     * }
     */
    private function checkSsl(string $finalUrl): array
    {
        $empty = [
            'enabled' => false,
            'valid' => false,
            'inspected' => false,
            'issuer' => null,
            'subject' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
        ];

        $scheme = Str::lower((string) parse_url($finalUrl, PHP_URL_SCHEME));
        $host = parse_url($finalUrl, PHP_URL_HOST);
        $port = parse_url($finalUrl, PHP_URL_PORT) ?: 443;

        if ($scheme !== 'https' || ! is_string($host) || $host === '') {
            return $empty;
        }

        // Homepage was already fetched over HTTPS — treat as reachable/valid baseline.
        $result = [
            'enabled' => true,
            'valid' => true,
            'inspected' => false,
            'issuer' => null,
            'subject' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
        ];

        // Skip reserved test TLDs — DNS/TLS probes can hang in CI/local.
        if (preg_match('/\.(test|localhost|invalid|example)$/i', $host) === 1) {
            return $result;
        }

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'capture_peer_cert_chain' => false,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => $host,
                ],
            ]);

            $client = @stream_socket_client(
                'ssl://'.$host.':'.$port,
                $errno,
                $errstr,
                5,
                STREAM_CLIENT_CONNECT,
                $context,
            );

            if (! is_resource($client)) {
                return $result;
            }

            $params = stream_context_get_params($client);
            fclose($client);

            $certResource = $params['options']['ssl']['peer_certificate'] ?? null;

            if ($certResource === null) {
                return $result;
            }

            $parsed = openssl_x509_parse($certResource);

            if (! is_array($parsed)) {
                return $result;
            }

            $validFrom = isset($parsed['validFrom_time_t'])
                ? (int) $parsed['validFrom_time_t']
                : null;
            $validTo = isset($parsed['validTo_time_t'])
                ? (int) $parsed['validTo_time_t']
                : null;

            $now = time();
            $stillValid = ($validFrom === null || $validFrom <= $now)
                && ($validTo === null || $validTo >= $now);

            $daysRemaining = null;

            if ($validTo !== null) {
                $daysRemaining = (int) floor(($validTo - $now) / 86400);
            }

            return [
                'enabled' => true,
                'valid' => $stillValid,
                'inspected' => true,
                'issuer' => $this->certificateName($parsed['issuer'] ?? null),
                'subject' => $this->certificateName($parsed['subject'] ?? null),
                'valid_from' => $validFrom !== null ? gmdate('Y-m-d', $validFrom) : null,
                'valid_to' => $validTo !== null ? gmdate('Y-m-d', $validTo) : null,
                'days_remaining' => $daysRemaining,
            ];
        } catch (Throwable) {
            return $result;
        }
    }

    /**
     * @param  array<string, mixed>|null  $name
     */
    private function certificateName(?array $name): ?string
    {
        if ($name === null || $name === []) {
            return null;
        }

        foreach (['CN', 'O', 'OU'] as $key) {
            if (! empty($name[$key]) && is_string($name[$key])) {
                return $name[$key];
            }
        }

        $first = reset($name);

        return is_string($first) && $first !== '' ? $first : null;
    }

    private function resolveIpAddress(string $finalUrl): ?string
    {
        $host = parse_url($finalUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        // Skip reserved test TLDs — DNS lookups can hang for minutes in CI/local.
        if (preg_match('/\.(test|localhost|invalid|example)$/i', $host) === 1) {
            return null;
        }

        $ip = gethostbyname($host);

        if ($ip === $host || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $ip;
    }

    /**
     * @return array{checked: bool, present: bool, from_host: ?string, to_host: ?string, http_status: ?int}
     */
    private function checkWwwRedirect(string $finalUrl): array
    {
        $parts = parse_url($finalUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return [
                'checked' => false,
                'present' => false,
                'from_host' => null,
                'to_host' => null,
                'http_status' => null,
            ];
        }

        $preferredHost = Str::lower((string) $parts['host']);
        $alternateHost = str_starts_with($preferredHost, 'www.')
            ? substr($preferredHost, 4)
            : 'www.'.$preferredHost;

        if ($alternateHost === '' || $alternateHost === $preferredHost) {
            return [
                'checked' => false,
                'present' => false,
                'from_host' => null,
                'to_host' => $preferredHost,
                'http_status' => null,
            ];
        }

        $alternateUrl = $parts['scheme'].'://'.$alternateHost
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .'/';

        try {
            $response = Http::withHeaders([
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                'User-Agent' => self::USER_AGENT,
            ])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->connectTimeout(3)
                ->timeout(8)
                ->get($alternateUrl);

            $effectiveHost = Str::lower((string) parse_url(
                (string) ($response->effectiveUri() ?? $alternateUrl),
                PHP_URL_HOST,
            ));

            return [
                'checked' => true,
                'present' => $effectiveHost === $preferredHost,
                'from_host' => $alternateHost,
                'to_host' => $preferredHost,
                'http_status' => $response->status(),
            ];
        } catch (Throwable) {
            return [
                'checked' => true,
                'present' => false,
                'from_host' => $alternateHost,
                'to_host' => $preferredHost,
                'http_status' => null,
            ];
        }
    }

    /**
     * @return array{checked: bool, url: ?string, http_status: ?int, returns_404: bool, home_link_present: bool}
     */
    private function checkNotFoundPage(string $finalUrl): array
    {
        $origin = $this->parser->originUrl($finalUrl);

        if ($origin === null) {
            return [
                'checked' => false,
                'url' => null,
                'http_status' => null,
                'returns_404' => false,
                'home_link_present' => false,
            ];
        }

        $probeUrl = $origin.'/set33-audit-404-'.Str::lower(Str::random(16));

        try {
            $page = $this->fetchHtml($probeUrl, throwOnError: false);
            $returns404 = $page['http_status'] === 404;
            $homeLinkPresent = false;

            if ($returns404 && filled($page['body'])) {
                $homeLinkPresent = $this->parser->hasHomeLink($page['body'], $finalUrl);
            }

            return [
                'checked' => true,
                'url' => $probeUrl,
                'http_status' => $page['http_status'],
                'returns_404' => $returns404,
                'home_link_present' => $homeLinkPresent,
            ];
        } catch (Throwable) {
            return [
                'checked' => true,
                'url' => $probeUrl,
                'http_status' => null,
                'returns_404' => false,
                'home_link_present' => false,
            ];
        }
    }

    /**
     * @return array{present: bool, http_status: ?int, content: ?string, indexing_allowed: ?bool}
     */
    private function fetchRobotsTxt(string $baseUrl): array
    {
        $origin = $this->parser->originUrl($baseUrl);

        if ($origin === null) {
            return [
                'present' => false,
                'http_status' => null,
                'content' => null,
                'indexing_allowed' => null,
            ];
        }

        $robotsUrl = $origin.'/robots.txt';

        try {
            $response = Http::withHeaders([
                'Accept' => 'text/plain,*/*;q=0.8',
                'User-Agent' => self::USER_AGENT,
            ])
                ->connectTimeout(3)
                ->timeout(8)
                ->get($robotsUrl);

            $status = $response->status();

            if (! $response->successful()) {
                return [
                    'present' => false,
                    'http_status' => $status,
                    'content' => null,
                    'indexing_allowed' => null,
                ];
            }

            $body = $response->body();

            if ($body === '' || strlen($body) > self::MAX_ROBOTS_BYTES) {
                return [
                    'present' => false,
                    'http_status' => $status,
                    'content' => null,
                    'indexing_allowed' => null,
                ];
            }

            $contentType = Str::lower((string) $response->header('Content-Type'));

            if ($contentType !== '' && ! str_contains($contentType, 'text/') && ! str_contains($contentType, 'json')) {
                if (! Str::contains($body, ['User-agent', 'user-agent', 'Sitemap', 'Disallow'], ignoreCase: true)) {
                    return [
                        'present' => false,
                        'http_status' => $status,
                        'content' => null,
                        'indexing_allowed' => null,
                    ];
                }
            }

            $content = Str::limit($body, self::MAX_ROBOTS_BYTES, '');

            return [
                'present' => true,
                'http_status' => $status,
                'content' => $content,
                'indexing_allowed' => $this->robotsAllowsIndexing($content),
            ];
        } catch (Throwable) {
            return [
                'present' => false,
                'http_status' => null,
                'content' => null,
                'indexing_allowed' => null,
            ];
        }
    }

    private function robotsAllowsIndexing(string $content): bool
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $inStarGroup = false;
        $starGroupSeen = false;
        $disallows = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $matches)) {
                $agent = trim($matches[1]);
                $inStarGroup = $agent === '*';
                if ($inStarGroup) {
                    $starGroupSeen = true;
                }

                continue;
            }

            if (! $inStarGroup) {
                continue;
            }

            if (preg_match('/^disallow:\s*(.*)$/i', $line, $matches)) {
                $disallows[] = trim($matches[1]);
            }
        }

        if (! $starGroupSeen) {
            return true;
        }

        foreach ($disallows as $path) {
            if ($path === '/') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{present: bool, http_status: ?int, source: ?string, url: ?string}
     */
    private function resolveSitemap(string $baseUrl, ?string $robotsContent): array
    {
        $candidates = [];

        if (filled($robotsContent) && preg_match_all('/^\s*Sitemap:\s*(\S+)/mi', $robotsContent, $matches)) {
            foreach ($matches[1] as $sitemapUrl) {
                $absolute = $this->parser->absolutizeUrl(trim($sitemapUrl), $baseUrl);

                if ($absolute !== null) {
                    $candidates[] = ['url' => $absolute, 'source' => 'robots'];
                }
            }
        }

        $origin = $this->parser->originUrl($baseUrl);

        if ($origin !== null) {
            $fallback = $origin.'/sitemap.xml';
            $alreadyListed = collect($candidates)->contains(fn (array $item): bool => $item['url'] === $fallback);

            if (! $alreadyListed) {
                $candidates[] = ['url' => $fallback, 'source' => 'sitemap.xml'];
            }
        }

        $lastFailed = ['present' => false, 'http_status' => null, 'source' => null, 'url' => null];

        foreach ($candidates as $candidate) {
            $result = $this->probeSitemap($candidate['url'], $candidate['source']);

            if ($result['present']) {
                return $result;
            }

            $lastFailed = $result;
        }

        return [
            'present' => false,
            'http_status' => $lastFailed['http_status'],
            'source' => null,
            'url' => $lastFailed['url'],
        ];
    }

    /**
     * @return array{present: bool, http_status: ?int, source: ?string, url: ?string}
     */
    private function probeSitemap(string $url, string $source): array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/xml,text/xml,*/*;q=0.8',
                'User-Agent' => self::USER_AGENT,
            ])
                ->connectTimeout(3)
                ->timeout(8)
                ->get($url);

            $status = $response->status();

            if (! $response->successful()) {
                return ['present' => false, 'http_status' => $status, 'source' => null, 'url' => $url];
            }

            $body = $response->body();

            if ($body === '' || strlen($body) > self::MAX_SITEMAP_BYTES) {
                return ['present' => false, 'http_status' => $status, 'source' => null, 'url' => $url];
            }

            $looksLikeSitemap = str_contains($body, '<urlset')
                || str_contains($body, '<sitemapindex')
                || str_contains(Str::lower($body), 'xmlns="http://www.sitemaps.org');

            if (! $looksLikeSitemap) {
                return ['present' => false, 'http_status' => $status, 'source' => null, 'url' => $url];
            }

            return [
                'present' => true,
                'http_status' => $status,
                'source' => $source,
                'url' => $url,
            ];
        } catch (Throwable) {
            return ['present' => false, 'http_status' => null, 'source' => null, 'url' => $url];
        }
    }

    private function urlsEqual(string $left, string $right): bool
    {
        return rtrim($left, '/') === rtrim($right, '/');
    }
}
