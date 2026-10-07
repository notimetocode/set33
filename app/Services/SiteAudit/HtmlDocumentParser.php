<?php

namespace App\Services\SiteAudit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class HtmlDocumentParser
{
    /**
     * @return array{
     *     title: ?string,
     *     meta_description: ?string,
     *     meta_keywords: ?string,
     *     html_lang: ?string,
     *     charset: ?string,
     *     viewport: ?string,
     *     canonical_url: ?string,
     *     og_title: ?string,
     *     og_description: ?string,
     *     og_image_url: ?string,
     *     meta_robots: array{content: ?string, indexing_allowed: bool},
     *     icons: array{favicon: bool, apple_touch_icon: bool, urls: list<string>},
     *     google_analytics: array{present: bool, ids: list<string>},
     *     yandex_metrika: array{present: bool, counter_ids: list<string>}
     * }
     */
    public function parse(string $html, string $baseUrl): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        $titleNode = $xpath->query('//title')->item(0);
        $pageTitle = $this->normalizeText($titleNode?->textContent);

        $ogTitle = $this->metaProperty($xpath, ['og:title']);
        $ogDescription = $this->metaProperty($xpath, ['og:description']);
        $ogImageUrl = $this->absolutizeUrl(
            $this->metaProperty($xpath, ['og:image', 'og:image:url']),
            $baseUrl,
        );

        return [
            'title' => $pageTitle,
            'meta_description' => $this->metaContent($xpath, ['description', 'Description']),
            'meta_keywords' => $this->metaContent($xpath, ['keywords', 'Keywords']),
            'html_lang' => $this->normalizeText(
                $document->documentElement?->getAttribute('lang') ?: null,
                32,
            ),
            'charset' => $this->detectCharset($xpath, $html),
            'viewport' => $this->metaContent($xpath, ['viewport', 'Viewport']),
            'canonical_url' => $this->absolutizeUrl(
                $this->linkHref($xpath, ['canonical']),
                $baseUrl,
            ),
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image_url' => $ogImageUrl,
            'meta_robots' => $this->detectMetaRobots($xpath),
            'icons' => $this->detectIcons($xpath, $baseUrl),
            'google_analytics' => $this->detectGoogleAnalytics($html),
            'yandex_metrika' => $this->detectYandexMetrika($html),
        ];
    }

    /**
     * @return array{content: ?string, indexing_allowed: bool}
     */
    public function detectMetaRobots(DOMXPath $xpath): array
    {
        $parts = array_filter([
            $this->metaContent($xpath, ['robots', 'Robots']),
            $this->metaContent($xpath, ['googlebot', 'Googlebot']),
        ]);

        $content = $parts === [] ? null : implode(', ', $parts);
        $lower = Str::lower((string) $content);

        return [
            'content' => $content,
            'indexing_allowed' => $content === null || ! str_contains($lower, 'noindex'),
        ];
    }

    /**
     * @return array{favicon: bool, apple_touch_icon: bool, urls: list<string>}
     */
    public function detectIcons(DOMXPath $xpath, string $baseUrl): array
    {
        $urls = [];
        $favicon = false;
        $appleTouchIcon = false;
        $nodes = $xpath->query('//link[@rel]');

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $rel = Str::lower(trim($node->getAttribute('rel')));
                $relTokens = preg_split('/\s+/', $rel) ?: [];
                $isApple = in_array('apple-touch-icon', $relTokens, true)
                    || in_array('apple-touch-icon-precomposed', $relTokens, true);
                $isIcon = in_array('icon', $relTokens, true)
                    || in_array('shortcut', $relTokens, true)
                    || $isApple
                    || str_contains($rel, 'icon');

                if (! $isIcon) {
                    continue;
                }

                if ($isApple) {
                    $appleTouchIcon = true;
                } else {
                    $favicon = true;
                }

                $href = $this->absolutizeUrl(
                    $this->normalizeText($node->getAttribute('href'), 2048),
                    $baseUrl,
                );

                if ($href !== null) {
                    $urls[] = $href;
                }
            }
        }

        return [
            'favicon' => $favicon,
            'apple_touch_icon' => $appleTouchIcon,
            'urls' => array_values(array_unique($urls)),
        ];
    }

    public function hasHomeLink(string $html, string $homeUrl): bool
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//a[@href]');

        if ($nodes === false) {
            return false;
        }

        $homeOrigin = $this->originUrl($homeUrl);
        $homePath = rtrim((string) (parse_url($homeUrl, PHP_URL_PATH) ?: '/'), '/') ?: '/';

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = $this->normalizeText($node->getAttribute('href'), 2048);

            if ($href === null) {
                continue;
            }

            if (in_array($href, ['/', './', '#'], true)) {
                return true;
            }

            $absolute = $this->absolutizeUrl($href, $homeUrl);

            if ($absolute === null) {
                continue;
            }

            $origin = $this->originUrl($absolute);
            $path = rtrim((string) (parse_url($absolute, PHP_URL_PATH) ?: '/'), '/') ?: '/';

            if ($homeOrigin !== null && $origin === $homeOrigin && ($path === '/' || $path === $homePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{present: bool, ids: list<string>}
     */
    public function detectGoogleAnalytics(string $html): array
    {
        $ids = [];

        if (preg_match_all('/\bGTM-[A-Z0-9]+\b/i', $html, $matches)) {
            foreach ($matches[0] as $id) {
                $ids[] = strtoupper($id);
            }
        }

        if (preg_match_all('/\bG-[A-Z0-9]+\b/', $html, $matches)) {
            foreach ($matches[0] as $id) {
                $ids[] = $id;
            }
        }

        if (preg_match_all('/\bUA-\d{4,}-\d+\b/i', $html, $matches)) {
            foreach ($matches[0] as $id) {
                $ids[] = strtoupper($id);
            }
        }

        $ids = array_values(array_unique($ids));

        $present = $ids !== []
            || str_contains($html, 'googletagmanager.com')
            || str_contains($html, 'google-analytics.com')
            || str_contains($html, 'gtag(')
            || str_contains($html, "ga('create'")
            || str_contains($html, 'ga("create"');

        return [
            'present' => $present,
            'ids' => $ids,
        ];
    }

    /**
     * @return array{present: bool, counter_ids: list<string>}
     */
    public function detectYandexMetrika(string $html): array
    {
        $counterIds = [];

        if (preg_match_all('/mc\.yandex\.ru\/(?:watch|metrika\/tag\.js)\?.*?id=(\d+)/i', $html, $matches)) {
            foreach ($matches[1] as $id) {
                $counterIds[] = $id;
            }
        }

        if (preg_match_all('/ym\(\s*(\d+)\s*,/i', $html, $matches)) {
            foreach ($matches[1] as $id) {
                $counterIds[] = $id;
            }
        }

        if (preg_match_all('/yaCounter(\d+)/i', $html, $matches)) {
            foreach ($matches[1] as $id) {
                $counterIds[] = $id;
            }
        }

        if (preg_match_all('/["\']id["\']\s*:\s*(\d+)/i', $html, $matches) && (
            str_contains($html, 'mc.yandex.ru')
            || str_contains($html, 'Ya.Metrika')
            || str_contains($html, 'metrika.yandex')
        )) {
            foreach ($matches[1] as $id) {
                $counterIds[] = $id;
            }
        }

        $counterIds = array_values(array_unique($counterIds));

        $present = $counterIds !== []
            || str_contains($html, 'mc.yandex.ru')
            || str_contains($html, 'metrika.yandex')
            || str_contains($html, 'ym(')
            || str_contains($html, 'Ya.Metrika')
            || str_contains(Str::lower($html), 'yacounter');

        return [
            'present' => $present,
            'counter_ids' => $counterIds,
        ];
    }

    private function detectCharset(DOMXPath $xpath, string $html): ?string
    {
        $metaCharset = $xpath->query('//meta[@charset]')->item(0);

        if ($metaCharset instanceof DOMElement) {
            $value = $this->normalizeText($metaCharset->getAttribute('charset'), 64);

            if ($value !== null) {
                return Str::lower($value);
            }
        }

        $httpEquiv = $this->metaHttpEquiv($xpath, ['content-type', 'Content-Type']);

        if ($httpEquiv !== null && preg_match('/charset\s*=\s*([^\s;]+)/i', $httpEquiv, $matches)) {
            return Str::lower(trim($matches[1], "\"'"));
        }

        if (preg_match('/<meta[^>]+charset=["\']?([^"\'\s>]+)/i', $html, $matches)) {
            return Str::lower($matches[1]);
        }

        return null;
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
     * @param  list<string>  $httpEquivs
     */
    private function metaHttpEquiv(DOMXPath $xpath, array $httpEquivs): ?string
    {
        foreach ($httpEquivs as $httpEquiv) {
            $nodes = $xpath->query(sprintf(
                '//meta[translate(@http-equiv, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")=%s]/@content',
                $this->xpathLiteral(Str::lower($httpEquiv)),
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
    public function metaProperty(DOMXPath $xpath, array $properties): ?string
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

    public function absolutizeUrl(?string $url, string $baseUrl): ?string
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

        return $origin.$directory.$url;
    }

    public function originUrl(string $url): ?string
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
