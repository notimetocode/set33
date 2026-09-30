<?php

namespace App\Actions\Site;

use App\Enums\SiteWebDataStatus;
use App\Models\Site;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CollectSiteWebData
{
    private const USER_AGENT = 'Set33SiteBot/1.0 (+https://set33.com)';

    private const MAX_HTML_BYTES = 1_500_000;

    private const MAX_FAVICON_BYTES = 512_000;

    private const MAX_ROBOTS_BYTES = 512_000;

    public function handle(Site $site): Site
    {
        $site->forceFill([
            'web_data_status' => SiteWebDataStatus::Pending,
            'web_data_error' => null,
        ])->save();

        try {
            $homepage = $this->fetchHomepage($site->url);
            $meta = $this->parseHomepageHtml($homepage['body'], $homepage['final_url']);
            $favicon = $this->storeFavicon($site, $meta['favicon_candidates'], $homepage['final_url']);
            $robotsTxt = $this->fetchRobotsTxt($homepage['final_url']);

            if (filled($site->favicon_path) && $site->favicon_path !== ($favicon['path'] ?? null)) {
                Storage::disk('public')->delete($site->favicon_path);
            }

            $site->forceFill([
                'web_data_status' => SiteWebDataStatus::Ready,
                'page_title' => $meta['page_title'],
                'meta_description' => $meta['meta_description'],
                'meta_keywords' => $meta['meta_keywords'],
                'og_title' => $meta['og_title'],
                'og_description' => $meta['og_description'],
                'og_image_url' => $meta['og_image_url'],
                'canonical_url' => $meta['canonical_url'],
                'html_lang' => $meta['html_lang'],
                'favicon_path' => $favicon['path'] ?? null,
                'favicon_source_url' => $favicon['source_url'] ?? null,
                'robots_txt' => $robotsTxt,
                'web_data_fetched_at' => now(),
                'web_data_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $site->forceFill([
                'web_data_status' => SiteWebDataStatus::Failed,
                'web_data_error' => Str::limit($exception->getMessage(), 2000),
                'web_data_fetched_at' => now(),
            ])->save();
        }

        return $site->refresh();
    }

    /**
     * @return array{body: string, final_url: string}
     */
    private function fetchHomepage(string $url): array
    {
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
            })
            ->get($url);

        if (! $response->successful()) {
            $response->throw();
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_HTML_BYTES) {
            $body = substr($body, 0, self::MAX_HTML_BYTES);
        }

        return [
            'body' => $body,
            'final_url' => (string) ($response->effectiveUri() ?? $url),
        ];
    }

    /**
     * @return array{
     *     page_title: ?string,
     *     meta_description: ?string,
     *     meta_keywords: ?string,
     *     og_title: ?string,
     *     og_description: ?string,
     *     og_image_url: ?string,
     *     canonical_url: ?string,
     *     html_lang: ?string,
     *     favicon_candidates: list<string>
     * }
     */
    private function parseHomepageHtml(string $html, string $baseUrl): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        $titleNode = $xpath->query('//title')->item(0);
        $pageTitle = $this->normalizeText($titleNode?->textContent);

        return [
            'page_title' => $pageTitle,
            'meta_description' => $this->metaContent($xpath, ['description', 'Description']),
            'meta_keywords' => $this->metaContent($xpath, ['keywords', 'Keywords']),
            'og_title' => $this->metaProperty($xpath, ['og:title']) ?? $pageTitle,
            'og_description' => $this->metaProperty($xpath, ['og:description']),
            'og_image_url' => $this->absolutizeUrl(
                $this->metaProperty($xpath, ['og:image', 'og:image:url']),
                $baseUrl,
            ),
            'canonical_url' => $this->absolutizeUrl(
                $this->linkHref($xpath, ['canonical']),
                $baseUrl,
            ),
            'html_lang' => $this->normalizeText(
                $document->documentElement?->getAttribute('lang') ?: null,
                32,
            ),
            'favicon_candidates' => $this->faviconCandidates($xpath, $baseUrl),
        ];
    }

    /**
     * @param  list<string>  $names
     */
    private function metaContent(DOMXPath $xpath, array $names): ?string
    {
        foreach ($names as $name) {
            $nodes = $xpath->query(sprintf(
                '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")=%s]/@content',
                $this->xpathLiteral(Str::lower($name)),
            ));

            $value = $this->normalizeText($nodes?->item(0)?->nodeValue, 2000);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $properties
     */
    private function metaProperty(DOMXPath $xpath, array $properties): ?string
    {
        foreach ($properties as $property) {
            $nodes = $xpath->query(sprintf(
                '//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")=%s]/@content',
                $this->xpathLiteral(Str::lower($property)),
            ));

            $value = $this->normalizeText($nodes?->item(0)?->nodeValue, 2000);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $rels
     */
    private function linkHref(DOMXPath $xpath, array $rels): ?string
    {
        foreach ($rels as $rel) {
            $nodes = $xpath->query('//link[@rel]');

            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $relTokens = preg_split('/\s+/', Str::lower(trim($node->getAttribute('rel')))) ?: [];

                if (! in_array(Str::lower($rel), $relTokens, true)) {
                    continue;
                }

                $href = $this->normalizeText($node->getAttribute('href'), 2048);

                if ($href !== null) {
                    return $href;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function faviconCandidates(DOMXPath $xpath, string $baseUrl): array
    {
        $candidates = [];
        $nodes = $xpath->query('//link[@rel]');

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $rel = Str::lower(trim($node->getAttribute('rel')));
                $relTokens = preg_split('/\s+/', $rel) ?: [];
                $isIcon = in_array('icon', $relTokens, true)
                    || in_array('apple-touch-icon', $relTokens, true)
                    || in_array('apple-touch-icon-precomposed', $relTokens, true)
                    || str_contains($rel, 'icon');

                if (! $isIcon) {
                    continue;
                }

                $href = $this->absolutizeUrl(
                    $this->normalizeText($node->getAttribute('href'), 2048),
                    $baseUrl,
                );

                if ($href !== null) {
                    $candidates[] = $href;
                }
            }
        }

        $fallback = $this->absolutizeUrl('/favicon.ico', $baseUrl);

        if ($fallback !== null) {
            $candidates[] = $fallback;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  list<string>  $candidates
     * @return array{path: string, source_url: string}|null
     */
    private function storeFavicon(Site $site, array $candidates, string $baseUrl): ?array
    {
        foreach ($candidates as $candidate) {
            $absolute = $this->absolutizeUrl($candidate, $baseUrl);

            if ($absolute === null) {
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'Accept' => 'image/*,*/*;q=0.8',
                    'User-Agent' => self::USER_AGENT,
                ])
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->get($absolute);

                if (! $response->successful()) {
                    continue;
                }

                $body = $response->body();

                if ($body === '' || strlen($body) > self::MAX_FAVICON_BYTES) {
                    continue;
                }

                $extension = $this->faviconExtension(
                    $response->header('Content-Type'),
                    $absolute,
                    $body,
                );

                if ($extension === null) {
                    continue;
                }

                $directory = 'sites/'.$site->id;
                $path = $directory.'/favicon.'.$extension;

                Storage::disk('public')->makeDirectory($directory);
                Storage::disk('public')->put($path, $body, 'public');

                return [
                    'path' => $path,
                    'source_url' => $absolute,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function fetchRobotsTxt(string $baseUrl): ?string
    {
        $robotsUrl = $this->originUrl($baseUrl);

        if ($robotsUrl === null) {
            return null;
        }

        $robotsUrl .= '/robots.txt';

        try {
            $response = Http::withHeaders([
                'Accept' => 'text/plain,*/*;q=0.8',
                'User-Agent' => self::USER_AGENT,
            ])
                ->connectTimeout(3)
                ->timeout(8)
                ->get($robotsUrl);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            if ($body === '' || strlen($body) > self::MAX_ROBOTS_BYTES) {
                return null;
            }

            $contentType = Str::lower((string) $response->header('Content-Type'));

            if ($contentType !== '' && ! str_contains($contentType, 'text/') && ! str_contains($contentType, 'json')) {
                // Some hosts still serve robots.txt as octet-stream; allow plain-looking bodies.
                if (! Str::contains($body, ['User-agent', 'user-agent', 'Sitemap', 'Disallow'], ignoreCase: true)) {
                    return null;
                }
            }

            return Str::limit($body, self::MAX_ROBOTS_BYTES, '');
        } catch (Throwable) {
            return null;
        }
    }

    private function faviconExtension(?string $contentType, string $url, string $body): ?string
    {
        $type = Str::lower((string) $contentType);

        if (str_contains($type, 'svg')) {
            return 'svg';
        }

        if (str_contains($type, 'png')) {
            return 'png';
        }

        if (str_contains($type, 'jpeg') || str_contains($type, 'jpg')) {
            return 'jpg';
        }

        if (str_contains($type, 'gif')) {
            return 'gif';
        }

        if (str_contains($type, 'webp')) {
            return 'webp';
        }

        if (str_contains($type, 'ico') || str_contains($type, 'x-icon')) {
            return 'ico';
        }

        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        foreach (['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'] as $extension) {
            if (Str::endsWith($path, '.'.$extension)) {
                return $extension === 'jpeg' ? 'jpg' : $extension;
            }
        }

        if (str_starts_with($body, "\x89PNG")) {
            return 'png';
        }

        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'jpg';
        }

        if (str_starts_with($body, 'GIF8')) {
            return 'gif';
        }

        if (str_contains(Str::lower(substr($body, 0, 200)), '<svg')) {
            return 'svg';
        }

        // ICO files often start with reserved zeros; accept as fallback.
        return 'ico';
    }

    private function absolutizeUrl(?string $url, string $baseUrl): ?string
    {
        $url = $this->normalizeText($url, 2048);

        if ($url === null) {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$url;
        }

        $parts = parse_url($baseUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (Str::startsWith($url, '/')) {
            return $origin.$url;
        }

        $basePath = $parts['path'] ?? '/';
        $directory = Str::endsWith($basePath, '/')
            ? $basePath
            : Str::beforeLast($basePath, '/').'/';

        if ($directory === '/') {
            $directory = '/';
        }

        return $origin.$directory.$url;
    }

    private function originUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function normalizeText(?string $value, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $max, '');
    }

    private function xpathLiteral(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        $parts = explode("'", $value);
        $concat = [];

        foreach ($parts as $index => $part) {
            $concat[] = "'{$part}'";

            if ($index < count($parts) - 1) {
                $concat[] = "\"'\"";
            }
        }

        return 'concat('.implode(', ', $concat).')';
    }
}
