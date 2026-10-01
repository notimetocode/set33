<?php

namespace App\Actions\Site;

use App\Models\Site;
use App\Models\SitePageSnapshot;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class CollectSitePageSnapshot
{
    private const USER_AGENT = 'Set33SiteBot/1.0 (+https://set33.com)';

    private const MAX_HTML_BYTES = 1_500_000;

    public function handle(Site $site, string $url): SitePageSnapshot
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('Некорректный URL для снимка страницы.');
        }

        $snapshot = SitePageSnapshot::query()->firstOrNew([
            'site_id' => $site->id,
            'url' => mb_substr($url, 0, 500),
        ]);

        try {
            $page = $this->fetchPage($url);
            $meta = $this->parseHtml($page['body'], $page['final_url']);

            $snapshot->forceFill([
                'final_url' => mb_substr($page['final_url'], 0, 768),
                'page_title' => $meta['page_title'],
                'meta_description' => $meta['meta_description'],
                'meta_keywords' => $meta['meta_keywords'],
                'og_title' => $meta['og_title'],
                'og_description' => $meta['og_description'],
                'og_image_url' => $meta['og_image_url'],
                'canonical_url' => $meta['canonical_url'],
                'html_lang' => $meta['html_lang'],
                'fetched_at' => now(),
                'error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $snapshot->forceFill([
                'fetched_at' => now(),
                'error' => Str::limit($exception->getMessage(), 2000),
            ])->save();

            throw $exception;
        }

        return $snapshot->refresh();
    }

    /**
     * @return array{body: string, final_url: string}
     */
    private function fetchPage(string $url): array
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
     *     html_lang: ?string
     * }
     */
    private function parseHtml(string $html, string $baseUrl): array
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
            'meta_description' => $this->metaContent($xpath, ['description']),
            'meta_keywords' => $this->metaContent($xpath, ['keywords']),
            'og_title' => $this->metaProperty($xpath, ['og:title']) ?? $pageTitle,
            'og_description' => $this->metaProperty($xpath, ['og:description']),
            'og_image_url' => $this->absolutizeUrl($this->metaProperty($xpath, ['og:image']), $baseUrl),
            'canonical_url' => $this->absolutizeUrl($this->linkHref($xpath), $baseUrl),
            'html_lang' => $this->normalizeText(
                $document->documentElement?->getAttribute('lang') ?: null,
                32,
            ),
        ];
    }

    /**
     * @param  list<string>  $names
     */
    private function metaContent(DOMXPath $xpath, array $names): ?string
    {
        foreach ($names as $name) {
            $nodes = $xpath->query(sprintf(
                '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")=%s]/@content',
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
                '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")=%s]/@content',
                $this->xpathLiteral(Str::lower($property)),
            ));
            $value = $this->normalizeText($nodes?->item(0)?->nodeValue, 2000);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function linkHref(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//link[@rel]');

        if ($nodes === false) {
            return null;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $relTokens = preg_split('/\s+/', Str::lower(trim($node->getAttribute('rel')))) ?: [];

            if (! in_array('canonical', $relTokens, true)) {
                continue;
            }

            return $this->normalizeText($node->getAttribute('href'), 2048);
        }

        return null;
    }

    private function absolutizeUrl(?string $value, string $baseUrl): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return mb_substr($value, 0, 768);
        }

        $base = parse_url($baseUrl);
        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        if (str_starts_with($value, '//')) {
            return mb_substr($base['scheme'].':'.$value, 0, 768);
        }

        if (str_starts_with($value, '/')) {
            $port = isset($base['port']) ? ':'.$base['port'] : '';

            return mb_substr($base['scheme'].'://'.$base['host'].$port.$value, 0, 768);
        }

        return null;
    }

    private function normalizeText(?string $value, int $max = 512): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
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
        $concat = array_map(static fn (string $part): string => "'{$part}'", $parts);

        return 'concat('.implode(', "\'", ', $concat).')';
    }
}
