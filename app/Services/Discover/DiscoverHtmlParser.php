<?php

namespace App\Services\Discover;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class DiscoverHtmlParser
{
    /**
     * @return array{
     *     robots: ?string,
     *     canonical: ?string,
     *     canonical_absolute: bool,
     *     amphtml: ?string,
     *     og_title: ?string,
     *     og_description: ?string,
     *     og_image: ?string,
     *     og_image_absolute: bool,
     *     og_image_width: ?string,
     *     og_image_height: ?string,
     *     og_image_type: ?string,
     *     og_url: ?string,
     *     og_url_absolute: bool,
     *     published_time: ?string,
     *     modified_time: ?string,
     *     author_links: list<string>,
     *     avtor_links: list<string>,
     *     news_articles: list<array{
     *         headline: ?string,
     *         image: ?string,
     *         date_published: ?string,
     *         date_modified: ?string,
     *         author_is_person: bool,
     *         author_urls: list<string>,
     *         publisher_has_logo: bool
     *     }>
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
        $robots = array_merge(
            $this->metaContents($xpath, 'name', 'robots'),
            $this->metaContents($xpath, 'name', 'googlebot'),
        );

        $canonicalRaw = $this->linkHref($xpath, 'canonical');
        $ogImageRaw = $this->metaProperty($xpath, 'og:image');
        $ogUrlRaw = $this->metaProperty($xpath, 'og:url');
        [$authorLinks, $avtorLinks] = $this->anchorLinks($xpath, $baseUrl);

        return [
            'robots' => $robots === [] ? null : implode(', ', $robots),
            'canonical' => $this->absolutize($canonicalRaw, $baseUrl),
            'canonical_absolute' => $this->isAbsoluteHttp($canonicalRaw),
            'amphtml' => $this->absolutize($this->linkHref($xpath, 'amphtml'), $baseUrl),
            'og_title' => $this->metaProperty($xpath, 'og:title'),
            'og_description' => $this->metaProperty($xpath, 'og:description'),
            'og_image' => $this->absolutize($ogImageRaw, $baseUrl),
            'og_image_absolute' => $this->isAbsoluteHttp($ogImageRaw),
            'og_image_width' => $this->metaProperty($xpath, 'og:image:width'),
            'og_image_height' => $this->metaProperty($xpath, 'og:image:height'),
            'og_image_type' => $this->metaProperty($xpath, 'og:image:type'),
            'og_url' => $this->absolutize($ogUrlRaw, $baseUrl),
            'og_url_absolute' => $this->isAbsoluteHttp($ogUrlRaw),
            'published_time' => $this->metaProperty($xpath, 'article:published_time'),
            'modified_time' => $this->metaProperty($xpath, 'article:modified_time'),
            'author_links' => $authorLinks,
            'avtor_links' => $avtorLinks,
            'news_articles' => $this->newsArticles($xpath, $baseUrl),
        ];
    }

    public function absolutize(?string $url, string $baseUrl): ?string
    {
        $url = $this->clean($url, 2048);

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

    public function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * @return list<string>
     */
    private function metaContents(DOMXPath $xpath, string $attribute, string $name): array
    {
        $nodes = $xpath->query('//meta[@'.$attribute.']');
        $values = [];

        if ($nodes === false) {
            return $values;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (strtolower($node->getAttribute($attribute)) !== $name) {
                continue;
            }

            $content = $this->clean($node->getAttribute('content'), 2000);

            if ($content !== null) {
                $values[] = $content;
            }
        }

        return $values;
    }

    private function metaProperty(DOMXPath $xpath, string $property): ?string
    {
        $values = $this->metaContents($xpath, 'property', $property);

        return $values[0] ?? null;
    }

    private function linkHref(DOMXPath $xpath, string $rel): ?string
    {
        $nodes = $xpath->query('//link[@rel]');

        if ($nodes === false) {
            return null;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $rels = preg_split('/\s+/', strtolower($node->getAttribute('rel'))) ?: [];

            if (! in_array($rel, $rels, true)) {
                continue;
            }

            return $this->clean($node->getAttribute('href'), 2048);
        }

        return null;
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function anchorLinks(DOMXPath $xpath, string $baseUrl): array
    {
        $authorLinks = [];
        $avtorLinks = [];
        $nodes = $xpath->query('//a[@href]');

        if ($nodes === false) {
            return [$authorLinks, $avtorLinks];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = $this->absolutize($node->getAttribute('href'), $baseUrl);

            if ($href === null) {
                continue;
            }

            $rels = preg_split('/\s+/', strtolower($node->getAttribute('rel'))) ?: [];

            if (in_array('author', $rels, true)) {
                $authorLinks[] = $href;
            }

            $path = parse_url($href, PHP_URL_PATH);

            if (is_string($path) && preg_match('#^/avtor-teksta(?:-ru)?/#', $path) === 1) {
                $avtorLinks[] = $href;
            }
        }

        return [array_values(array_unique($authorLinks)), array_values(array_unique($avtorLinks))];
    }

    /**
     * @return list<array{
     *     headline: ?string,
     *     image: ?string,
     *     date_published: ?string,
     *     date_modified: ?string,
     *     author_is_person: bool,
     *     author_urls: list<string>,
     *     publisher_has_logo: bool
     * }>
     */
    private function newsArticles(DOMXPath $xpath, string $baseUrl): array
    {
        $nodes = $xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "ld+json")]');
        $articles = [];

        if ($nodes === false) {
            return $articles;
        }

        foreach ($nodes as $node) {
            $decoded = json_decode(trim($node->textContent), true);

            if (! is_array($decoded)) {
                continue;
            }

            $this->collectNewsArticles($decoded, $baseUrl, $articles);
        }

        return $articles;
    }

    /**
     * @param  list<array{
     *     headline: ?string,
     *     image: ?string,
     *     date_published: ?string,
     *     date_modified: ?string,
     *     author_is_person: bool,
     *     author_urls: list<string>,
     *     publisher_has_logo: bool
     * }>  $articles
     */
    private function collectNewsArticles(mixed $node, string $baseUrl, array &$articles): void
    {
        if (! is_array($node)) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $item) {
                $this->collectNewsArticles($item, $baseUrl, $articles);
            }

            return;
        }

        if (isset($node['@graph']) && is_array($node['@graph'])) {
            $this->collectNewsArticles($node['@graph'], $baseUrl, $articles);
        }

        if ($this->typeIncludes($node['@type'] ?? null, 'NewsArticle')) {
            $articles[] = $this->normalizeArticle($node, $baseUrl);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{
     *     headline: ?string,
     *     image: ?string,
     *     date_published: ?string,
     *     date_modified: ?string,
     *     author_is_person: bool,
     *     author_urls: list<string>,
     *     publisher_has_logo: bool
     * }
     */
    private function normalizeArticle(array $node, string $baseUrl): array
    {
        $authors = $node['author'] ?? null;
        $authorList = [];

        if (is_array($authors) && array_is_list($authors)) {
            $authorList = $authors;
        } elseif ($authors !== null) {
            $authorList = [$authors];
        }

        $authorIsPerson = false;
        $authorUrls = [];

        foreach ($authorList as $author) {
            if (! is_array($author)) {
                continue;
            }

            if ($this->typeIncludes($author['@type'] ?? null, 'Person')) {
                $authorIsPerson = true;
            }

            $url = $author['url'] ?? null;

            if (is_string($url) && $url !== '') {
                $absolute = $this->absolutize($url, $baseUrl);

                if ($absolute !== null) {
                    $authorUrls[] = $absolute;
                }
            }
        }

        return [
            'headline' => $this->textValue($node['headline'] ?? null),
            'image' => $this->firstImageUrl($node['image'] ?? null, $baseUrl),
            'date_published' => $this->textValue($node['datePublished'] ?? null),
            'date_modified' => $this->textValue($node['dateModified'] ?? null),
            'author_is_person' => $authorIsPerson,
            'author_urls' => array_values(array_unique($authorUrls)),
            'publisher_has_logo' => $this->publisherHasLogo($node['publisher'] ?? null),
        ];
    }

    private function typeIncludes(mixed $type, string $expected): bool
    {
        $types = is_array($type) ? $type : [$type];

        foreach ($types as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            if (strcasecmp(Str::afterLast($value, '/'), $expected) === 0) {
                return true;
            }
        }

        return false;
    }

    private function textValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function firstImageUrl(mixed $image, string $baseUrl): ?string
    {
        if (is_string($image)) {
            return $this->absolutize($image, $baseUrl);
        }

        if (! is_array($image)) {
            return null;
        }

        if (array_is_list($image)) {
            foreach ($image as $item) {
                $url = $this->firstImageUrl($item, $baseUrl);

                if ($url !== null) {
                    return $url;
                }
            }

            return null;
        }

        foreach (['url', 'contentUrl'] as $key) {
            if (isset($image[$key]) && is_string($image[$key]) && $image[$key] !== '') {
                return $this->absolutize($image[$key], $baseUrl);
            }
        }

        return null;
    }

    private function publisherHasLogo(mixed $publisher): bool
    {
        if (! is_array($publisher)) {
            return false;
        }

        if (array_is_list($publisher)) {
            foreach ($publisher as $item) {
                if ($this->publisherHasLogo($item)) {
                    return true;
                }
            }

            return false;
        }

        return $this->logoPresent($publisher['logo'] ?? null);
    }

    private function logoPresent(mixed $logo): bool
    {
        if (is_string($logo)) {
            return trim($logo) !== '';
        }

        if (! is_array($logo)) {
            return false;
        }

        if (array_is_list($logo)) {
            foreach ($logo as $item) {
                if ($this->logoPresent($item)) {
                    return true;
                }
            }

            return false;
        }

        foreach (['url', 'contentUrl'] as $key) {
            if (isset($logo[$key]) && is_string($logo[$key]) && trim($logo[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    private function isAbsoluteHttp(?string $url): bool
    {
        return is_string($url) && preg_match('#^https?://#i', trim($url)) === 1;
    }

    private function clean(?string $value, int $max): ?string
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
}
