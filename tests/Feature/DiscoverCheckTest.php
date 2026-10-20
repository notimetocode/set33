<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoverCheckTest extends TestCase
{
    public function test_public_api_returns_passing_discover_report(): void
    {
        $this->fakeArticle($this->widePng());

        $this->postJson('/api/public/discover-checks', [
            'url' => 'https://example.test/article',
        ])
            ->assertOk()
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.final_url', 'https://example.test/article')
            ->assertJsonPath('data.documentation_url', url('/docs/discover'))
            ->assertJsonPath('data.summary.pass', 12)
            ->assertJsonPath('data.summary.fail', 0)
            ->assertJsonPath('data.summary.warning', 0)
            ->assertJsonPath('data.summary.error', 0)
            ->assertJsonPath('data.summary.skipped', 0)
            ->assertJsonPath('data.checks.0.id', 'availability')
            ->assertJsonPath('data.checks.0.status', 'pass')
            ->assertJsonPath('data.checks.0.message', __('public.discover_check.availability_pass', ['status' => 200]))
            ->assertJsonPath('data.checks.4.status', 'pass')
            ->assertJsonPath('data.checks.4.details.present', false)
            ->assertJsonPath('data.checks.6.id', 'core_web_vitals')
            ->assertJsonPath('data.checks.6.status', 'pass')
            ->assertJsonPath('data.checks.6.details.lcp_ms', 2000)
            ->assertJsonPath('data.checks.6.details.lcp_source', 'crux')
            ->assertJsonPath('data.checks.6.details.inp_source', 'crux')
            ->assertJsonPath('data.checks.6.details.cls_source', 'crux');

        $pagespeed = collect(Http::recorded())
            ->map(fn (array $pair): string => $pair[0]->url())
            ->first(fn (string $url): bool => str_contains($url, 'pagespeedonline'));

        $this->assertIsString($pagespeed);
        $this->assertStringContainsString('strategy=mobile', $pagespeed);
        $this->assertStringContainsString('category=performance', $pagespeed);
        $this->assertStringNotContainsString('category=seo', $pagespeed);
    }

    public function test_skip_omits_pagespeed_call(): void
    {
        $this->fakeArticle($this->widePng());

        $this->postJson('/api/public/discover-checks', [
            'url' => 'example.test/article',
            'skip' => ['core_web_vitals'],
        ])
            ->assertOk()
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.summary.pass', 11)
            ->assertJsonPath('data.summary.skipped', 1)
            ->assertJsonPath('data.checks.6.id', 'core_web_vitals')
            ->assertJsonPath('data.checks.6.status', 'skipped')
            ->assertJsonPath('data.checks.6.message', __('public.discover_check.skipped'));

        $calledPageSpeed = collect(Http::recorded())
            ->contains(fn (array $pair): bool => str_contains($pair[0]->url(), 'pagespeedonline'));

        $this->assertFalse($calledPageSpeed);
    }

    public function test_unavailable_page_fails_the_report(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response('missing', 404));

        $this->postJson('/api/public/discover-checks', [
            'url' => 'https://example.test/article',
            'skip' => ['core_web_vitals'],
        ])
            ->assertOk()
            ->assertJsonPath('data.passed', false)
            ->assertJsonPath('data.checks.0.status', 'fail')
            ->assertJsonPath('data.checks.0.message', __('public.discover_check.availability_fail', ['status' => 404]));
    }

    public function test_localhost_is_rejected(): void
    {
        $this->postJson('/api/public/discover-checks', [
            'url' => 'http://localhost/article',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_unknown_skip_id_is_rejected(): void
    {
        $this->postJson('/api/public/discover-checks', [
            'url' => 'https://example.test/article',
            'skip' => ['not-a-check'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skip.0']);
    }

    public function test_get_request_skips_pagespeed(): void
    {
        $this->fakeArticle($this->widePng());

        $this->getJson('/api/public/discover-checks?'.http_build_query([
            'url' => 'https://example.test/article',
            'skip' => 'core_web_vitals,amp',
        ]))
            ->assertOk()
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.summary.skipped', 2)
            ->assertJsonPath('data.checks.4.status', 'skipped')
            ->assertJsonPath('data.checks.6.status', 'skipped');

        $calledPageSpeed = collect(Http::recorded())
            ->contains(fn (array $pair): bool => str_contains($pair[0]->url(), 'pagespeedonline'));

        $this->assertFalse($calledPageSpeed);
    }

    public function test_docs_page_describes_the_endpoint(): void
    {
        $this->get('/docs/discover')
            ->assertOk()
            ->assertSee('/api/public/discover-checks', false)
            ->assertSee('core_web_vitals', false)
            ->assertSee('skip', false)
            ->assertSee('/docs/discover.md', false)
            ->assertSee('curl -s -G', false);

        $this->get('/ru/docs/discover')
            ->assertOk()
            ->assertSee('Проверка URL для Google Discover', false)
            ->assertSee('/ru/docs/discover.md', false);

        $this->get('/docs/discover.md')
            ->assertOk()
            ->assertHeader('content-type', 'text/markdown; charset=UTF-8')
            ->assertSee('# Google Discover URL check', false)
            ->assertSee('GET or POST', false)
            ->assertSee('/api/public/discover-checks', false);

        $this->get('/ru/docs/discover.md')
            ->assertOk()
            ->assertSee('# Проверка URL для Google Discover', false);
    }

    private function fakeArticle(string $png): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) use ($png) {
            $url = $request->url();

            if (str_contains($url, 'pagespeedonline')) {
                return Http::response([
                    'lighthouseResult' => [
                        'categories' => [
                            'performance' => ['score' => 0.9],
                        ],
                        'audits' => [
                            'largest-contentful-paint' => ['numericValue' => 1800],
                            'interaction-to-next-paint' => ['numericValue' => 120],
                            'cumulative-layout-shift' => ['numericValue' => 0.02],
                        ],
                    ],
                    'loadingExperience' => [
                        'id' => 'https://example.test/article',
                        'overall_category' => 'FAST',
                        'metrics' => [
                            'LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 2000],
                            'INTERACTION_TO_NEXT_PAINT' => ['percentile' => 150],
                            'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 0.05],
                        ],
                    ],
                ], 200);
            }

            $path = parse_url($url, PHP_URL_PATH) ?: '/';

            return match ($path) {
                '/article' => Http::response($this->articleHtml(), 200),
                '/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
                '/sitemap.xml', '/xml-google-1/', '/news-sitemap.xml' => Http::response($this->sitemapXml(), 200),
                '/cover.webp' => Http::response($png, 200, ['Content-Type' => 'image/png']),
                '/avtor-teksta/ivan', '/avtor-teksta/ivan/' => Http::response('Author', 200),
                default => Http::response('missing', 404),
            };
        });
    }

    private function articleHtml(): string
    {
        return <<<'HTML'
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Story</title>
            <meta name="robots" content="index, follow, max-image-preview:large">
            <link rel="canonical" href="https://example.test/article">
            <meta property="og:title" content="Story">
            <meta property="og:description" content="A story">
            <meta property="og:image" content="https://example.test/cover.webp">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="675">
            <meta property="og:image:type" content="image/webp">
            <meta property="og:url" content="https://example.test/article">
            <meta property="article:published_time" content="2026-10-19T11:56:07+05:00">
            <meta property="article:modified_time" content="2026-10-19T11:56:07+05:00">
            <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "NewsArticle",
                "headline": "Story",
                "image": "https://example.test/cover.webp",
                "datePublished": "2026-10-19T11:56:07+05:00",
                "dateModified": "2026-10-19T11:56:07+05:00",
                "author": {
                    "@type": "Person",
                    "name": "Ivan",
                    "url": "https://example.test/avtor-teksta/ivan/"
                },
                "publisher": {
                    "@type": "Organization",
                    "name": "Example",
                    "logo": {
                        "@type": "ImageObject",
                        "url": "https://example.test/logo.png"
                    }
                }
            }
            </script>
        </head>
        <body>
            <a rel="author" href="https://example.test/avtor-teksta/ivan/">Ivan</a>
        </body>
        </html>
        HTML;
    }

    private function sitemapXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            <url><loc>https://example.test/article</loc></url>
        </urlset>
        XML;
    }

    private function widePng(): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            $this->markTestSkipped('GD is required to build a cover image.');
        }

        $image = imagecreatetruecolor(1200, 675);
        ob_start();
        imagepng($image);
        imagedestroy($image);
        $bytes = ob_get_clean();

        $this->assertIsString($bytes);

        return $bytes;
    }
}
