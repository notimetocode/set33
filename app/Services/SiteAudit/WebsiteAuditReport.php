<?php

namespace App\Services\SiteAudit;

use Illuminate\Support\Carbon;

readonly class WebsiteAuditReport
{
    /**
     * @param  array{
     *     enabled: bool,
     *     valid: bool,
     *     inspected: bool,
     *     issuer: ?string,
     *     subject: ?string,
     *     valid_from: ?string,
     *     valid_to: ?string,
     *     days_remaining: ?int
     * }  $ssl
     * @param  array{checked: bool, present: bool, from_host: ?string, to_host: ?string, http_status: ?int}  $wwwRedirect
     * @param  array{checked: bool, url: ?string, http_status: ?int, returns_404: bool, home_link_present: bool}  $notFoundPage
     * @param  array{content: ?string, indexing_allowed: bool}  $metaRobots
     * @param  array{present: bool, http_status: ?int, content: ?string, indexing_allowed: ?bool}  $robotsTxt
     * @param  array{present: bool, http_status: ?int, source: ?string, url: ?string}  $sitemap
     * @param  array{favicon: bool, apple_touch_icon: bool, urls: list<string>}  $icons
     * @param  array{present: bool, ids: list<string>}  $googleAnalytics
     * @param  array{present: bool, counter_ids: list<string>}  $yandexMetrika
     * @param  array<string, mixed>  $content
     */
    public function __construct(
        public string $requestedUrl,
        public ?string $finalUrl,
        public Carbon $fetchedAt,
        public string $status,
        public ?string $error,
        public ?int $httpStatus,
        public ?int $responseTimeMs,
        public bool $redirected,
        public ?string $ipAddress,
        public array $ssl,
        public bool $httpsRedirect,
        public array $wwwRedirect,
        public array $notFoundPage,
        public array $metaRobots,
        public array $robotsTxt,
        public array $sitemap,
        public ?string $title,
        public bool $titlePresent,
        public ?string $metaDescription,
        public bool $metaDescriptionPresent,
        public ?string $metaKeywords,
        public ?string $htmlLang,
        public ?string $charset,
        public ?string $viewport,
        public bool $viewportPresent,
        public ?string $canonicalUrl,
        public bool $canonicalPresent,
        public ?string $ogTitle,
        public bool $ogTitlePresent,
        public ?string $ogDescription,
        public bool $ogDescriptionPresent,
        public ?string $ogImageUrl,
        public bool $ogImagePresent,
        public bool $openGraphPresent,
        public array $icons,
        public array $googleAnalytics,
        public array $yandexMetrika,
        public array $content,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requested_url' => $this->requestedUrl,
            'final_url' => $this->finalUrl,
            'fetched_at' => $this->fetchedAt->toIso8601String(),
            'status' => $this->status,
            'error' => $this->error,
            'http_status' => $this->httpStatus,
            'response_time_ms' => $this->responseTimeMs,
            'redirected' => $this->redirected,
            'ip_address' => $this->ipAddress,
            'ssl' => $this->ssl,
            'https' => $this->ssl['enabled'],
            'https_redirect' => $this->httpsRedirect,
            'www_redirect' => $this->wwwRedirect,
            'not_found_page' => $this->notFoundPage,
            'meta_robots' => $this->metaRobots,
            'robots_txt' => $this->robotsTxt,
            'sitemap' => $this->sitemap,
            'title' => $this->title,
            'title_present' => $this->titlePresent,
            'meta_description' => $this->metaDescription,
            'meta_description_present' => $this->metaDescriptionPresent,
            'meta_keywords' => $this->metaKeywords,
            'html_lang' => $this->htmlLang,
            'charset' => $this->charset,
            'viewport' => $this->viewport,
            'viewport_present' => $this->viewportPresent,
            'canonical_url' => $this->canonicalUrl,
            'canonical_present' => $this->canonicalPresent,
            'og_title' => $this->ogTitle,
            'og_title_present' => $this->ogTitlePresent,
            'og_description' => $this->ogDescription,
            'og_description_present' => $this->ogDescriptionPresent,
            'og_image_url' => $this->ogImageUrl,
            'og_image_present' => $this->ogImagePresent,
            'open_graph_present' => $this->openGraphPresent,
            'icons' => $this->icons,
            'favicon_present' => $this->icons['favicon'],
            'favicon_url' => $this->icons['urls'][0] ?? null,
            'google_analytics' => $this->googleAnalytics,
            'yandex_metrika' => $this->yandexMetrika,
            'content' => $this->content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyContent(): array
    {
        return [
            'title' => ['text' => null, 'length' => 0, 'count' => 0],
            'description' => ['text' => null, 'length' => 0, 'count' => 0],
            'headings' => [
                'counts' => ['h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0],
                'items' => [],
            ],
            'text_length' => 0,
            'word_count' => 0,
            'nausea' => 0.0,
            'html_size_bytes' => 0,
            'external_links' => ['total' => 0, 'indexable' => 0],
            'internal_links' => ['total' => 0, 'indexable' => 0],
            'adult_content' => false,
            'open_graph' => [
                'present' => false,
                'title' => null,
                'description' => null,
                'image_url' => null,
            ],
            'schema_org' => [
                'present' => false,
                'types' => [],
                'formats' => [],
                'json_ld_count' => 0,
                'microdata_count' => 0,
                'items' => [],
            ],
        ];
    }

    public static function failed(string $requestedUrl, string $error, ?int $httpStatus = null, ?int $responseTimeMs = null): self
    {
        return new self(
            requestedUrl: $requestedUrl,
            finalUrl: null,
            fetchedAt: now(),
            status: 'failed',
            error: $error,
            httpStatus: $httpStatus,
            responseTimeMs: $responseTimeMs,
            redirected: false,
            ipAddress: null,
            ssl: [
                'enabled' => str_starts_with(strtolower($requestedUrl), 'https://'),
                'valid' => false,
                'inspected' => false,
                'issuer' => null,
                'subject' => null,
                'valid_from' => null,
                'valid_to' => null,
                'days_remaining' => null,
            ],
            httpsRedirect: false,
            wwwRedirect: [
                'checked' => false,
                'present' => false,
                'from_host' => null,
                'to_host' => null,
                'http_status' => null,
            ],
            notFoundPage: [
                'checked' => false,
                'url' => null,
                'http_status' => null,
                'returns_404' => false,
                'home_link_present' => false,
            ],
            metaRobots: ['content' => null, 'indexing_allowed' => true],
            robotsTxt: [
                'present' => false,
                'http_status' => null,
                'content' => null,
                'indexing_allowed' => null,
            ],
            sitemap: ['present' => false, 'http_status' => null, 'source' => null, 'url' => null],
            title: null,
            titlePresent: false,
            metaDescription: null,
            metaDescriptionPresent: false,
            metaKeywords: null,
            htmlLang: null,
            charset: null,
            viewport: null,
            viewportPresent: false,
            canonicalUrl: null,
            canonicalPresent: false,
            ogTitle: null,
            ogTitlePresent: false,
            ogDescription: null,
            ogDescriptionPresent: false,
            ogImageUrl: null,
            ogImagePresent: false,
            openGraphPresent: false,
            icons: ['favicon' => false, 'apple_touch_icon' => false, 'urls' => []],
            googleAnalytics: ['present' => false, 'ids' => []],
            yandexMetrika: ['present' => false, 'counter_ids' => []],
            content: self::emptyContent(),
        );
    }
}
