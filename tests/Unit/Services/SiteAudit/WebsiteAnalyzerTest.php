<?php

namespace Tests\Unit\Services\SiteAudit;

use App\Services\SiteAudit\WebsiteAnalyzer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteAnalyzerTest extends TestCase
{
    public function test_analyzes_full_homepage_with_server_indexing_and_metadata(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($url === 'https://example.test' || $url === 'https://example.test/') {
                return Http::response(
                    <<<'HTML'
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <meta name="robots" content="index, follow">
                        <title>Example Site</title>
                        <meta name="description" content="A demo description">
                        <meta property="og:title" content="OG Example">
                        <meta property="og:description" content="OG description">
                        <meta property="og:image" content="/og.png">
                        <link rel="canonical" href="https://example.test/">
                        <link rel="icon" href="/icon.png" type="image/png">
                        <link rel="apple-touch-icon" href="/apple.png">
                        <script type="application/ld+json">{"@type":"Organization","name":"Example"}</script>
                        <script async src="https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ"></script>
                        <script>ym(12345678, "init", {});</script>
                    </head>
                    <body>
                        <h1>Main heading</h1>
                        <h2>Subheading</h2>
                        <p>Hello world content for the page analysis sample text.</p>
                        <a href="/about">About</a>
                        <a href="https://external.test/page" rel="nofollow">External</a>
                    </body>
                    </html>
                    HTML,
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8'],
                );
            }

            if (str_starts_with($url, 'https://www.example.test')) {
                return Http::response('', 301, ['Location' => 'https://example.test/']);
            }

            if (str_contains($url, '/set33-audit-404-')) {
                return Http::response(
                    '<html><body><a href="/">Home</a></body></html>',
                    404,
                );
            }

            if ($url === 'https://example.test/robots.txt') {
                return Http::response(
                    "User-agent: *\nDisallow: /admin\nSitemap: https://example.test/sitemap.xml\n",
                    200,
                    ['Content-Type' => 'text/plain'],
                );
            }

            if ($url === 'https://example.test/sitemap.xml') {
                return Http::response(
                    '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.test/</loc></url></urlset>',
                    200,
                    ['Content-Type' => 'application/xml'],
                );
            }

            return Http::response('Not Found', 404);
        });

        $data = (new WebsiteAnalyzer)->analyze('https://example.test')->toArray();

        $this->assertSame('ready', $data['status']);
        $this->assertSame(200, $data['http_status']);
        $this->assertTrue($data['ssl']['enabled']);
        $this->assertTrue($data['ssl']['valid']);
        $this->assertTrue($data['www_redirect']['checked']);
        $this->assertTrue($data['www_redirect']['present']);
        $this->assertTrue($data['not_found_page']['returns_404']);
        $this->assertTrue($data['not_found_page']['home_link_present']);
        $this->assertTrue($data['meta_robots']['indexing_allowed']);
        $this->assertTrue($data['robots_txt']['present']);
        $this->assertTrue($data['robots_txt']['indexing_allowed']);
        $this->assertTrue($data['sitemap']['present']);
        $this->assertTrue($data['title_present']);
        $this->assertTrue($data['og_title_present']);
        $this->assertTrue($data['icons']['favicon']);
        $this->assertTrue($data['icons']['apple_touch_icon']);
        $this->assertTrue($data['google_analytics']['present']);
        $this->assertTrue($data['yandex_metrika']['present']);
        $this->assertSame(1, $data['content']['title']['count']);
        $this->assertSame(1, $data['content']['headings']['counts']['h1']);
        $this->assertGreaterThan(0, $data['content']['word_count']);
        $this->assertSame(1, $data['content']['internal_links']['total']);
        $this->assertSame(1, $data['content']['external_links']['total']);
        $this->assertSame(0, $data['content']['external_links']['indexable']);
        $this->assertTrue($data['content']['schema_org']['present']);
        $this->assertContains('Organization', $data['content']['schema_org']['types']);
        $this->assertContains('json-ld', $data['content']['schema_org']['formats']);
        $this->assertSame(1, $data['content']['schema_org']['json_ld_count']);
        $this->assertNotEmpty($data['content']['schema_org']['items']);
        $this->assertTrue($data['content']['open_graph']['present']);
    }

    public function test_detects_noindex_and_robots_disallow_root(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://blocked.test') && ! str_contains($url, '/')) {
                // fallthrough
            }

            if ($url === 'https://blocked.test' || $url === 'https://blocked.test/') {
                return Http::response(
                    '<html><head><meta name="robots" content="noindex, nofollow"><title>Blocked</title></head><body></body></html>',
                    200,
                );
            }

            if (str_starts_with($url, 'https://www.blocked.test')) {
                return Http::response('ok', 200);
            }

            if (str_contains($url, '/set33-audit-404-')) {
                return Http::response('missing', 200);
            }

            if ($url === 'https://blocked.test/robots.txt') {
                return Http::response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
            }

            if ($url === 'https://blocked.test/sitemap.xml') {
                return Http::response('Not Found', 404);
            }

            return Http::response('Not Found', 404);
        });

        $data = (new WebsiteAnalyzer)->analyze('https://blocked.test')->toArray();

        $this->assertFalse($data['meta_robots']['indexing_allowed']);
        $this->assertTrue($data['robots_txt']['present']);
        $this->assertFalse($data['robots_txt']['indexing_allowed']);
        $this->assertFalse($data['not_found_page']['returns_404']);
        $this->assertFalse($data['www_redirect']['present']);
    }

    public function test_analyzes_minimal_html_without_optional_signals(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($url === 'https://minimal.test' || $url === 'https://minimal.test/') {
                return Http::response(
                    '<!DOCTYPE html><html><head></head><body>Hi</body></html>',
                    200,
                    ['Content-Type' => 'text/html'],
                );
            }

            if (str_starts_with($url, 'https://www.minimal.test')) {
                return Http::response('', 301, ['Location' => 'https://minimal.test/']);
            }

            if (str_contains($url, '/set33-audit-404-')) {
                return Http::response('Not Found', 404);
            }

            return Http::response('Not Found', 404);
        });

        $data = (new WebsiteAnalyzer)->analyze('https://minimal.test')->toArray();

        $this->assertSame('ready', $data['status']);
        $this->assertFalse($data['title_present']);
        $this->assertFalse($data['meta_description_present']);
        $this->assertFalse($data['icons']['favicon']);
        $this->assertFalse($data['robots_txt']['present']);
        $this->assertFalse($data['sitemap']['present']);
        $this->assertFalse($data['google_analytics']['present']);
        $this->assertTrue($data['meta_robots']['indexing_allowed']);
        $this->assertTrue($data['not_found_page']['returns_404']);
        $this->assertFalse($data['not_found_page']['home_link_present']);
    }

    public function test_failed_homepage_returns_failed_report(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'https://down.test' => Http::response('Server Error', 503),
        ]);

        $data = (new WebsiteAnalyzer)->analyze('https://down.test')->toArray();

        $this->assertSame('failed', $data['status']);
        $this->assertNotNull($data['error']);
        $this->assertSame(503, $data['http_status']);
        $this->assertFalse($data['robots_txt']['present']);
    }

    public function test_connection_failure_returns_failed_report(): void
    {
        Http::preventStrayRequests();

        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $data = (new WebsiteAnalyzer)->analyze('https://timeout.test')->toArray();

        $this->assertSame('failed', $data['status']);
        $this->assertStringContainsString('Connection timed out', (string) $data['error']);
    }

    public function test_falls_back_to_sitemap_xml_when_robots_has_no_sitemap_directive(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($url === 'https://fallback.test' || $url === 'https://fallback.test/') {
                return Http::response(
                    '<!DOCTYPE html><html><head><title>Fallback</title></head><body></body></html>',
                    200,
                );
            }

            if (str_starts_with($url, 'https://www.fallback.test')) {
                return Http::response('', 301, ['Location' => 'https://fallback.test/']);
            }

            if (str_contains($url, '/set33-audit-404-')) {
                return Http::response('Not Found', 404);
            }

            if ($url === 'https://fallback.test/robots.txt') {
                return Http::response("User-agent: *\nAllow: /\n", 200, ['Content-Type' => 'text/plain']);
            }

            if ($url === 'https://fallback.test/sitemap.xml') {
                return Http::response(
                    '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                    200,
                    ['Content-Type' => 'application/xml'],
                );
            }

            return Http::response('Not Found', 404);
        });

        $data = (new WebsiteAnalyzer)->analyze('https://fallback.test')->toArray();

        $this->assertTrue($data['robots_txt']['present']);
        $this->assertTrue($data['sitemap']['present']);
        $this->assertSame('sitemap.xml', $data['sitemap']['source']);
    }

    public function test_detects_https_redirect_from_http_url(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($url === 'http://secure.test' || $url === 'http://secure.test/') {
                return Http::response('', 301, ['Location' => 'https://secure.test/']);
            }

            if ($url === 'https://secure.test' || $url === 'https://secure.test/') {
                return Http::response(
                    '<!DOCTYPE html><html><head><title>Secure</title></head><body></body></html>',
                    200,
                    ['Content-Type' => 'text/html'],
                );
            }

            if (str_starts_with($url, 'https://www.secure.test') || str_starts_with($url, 'http://www.secure.test')) {
                return Http::response('', 301, ['Location' => 'https://secure.test/']);
            }

            if (str_contains($url, '/set33-audit-404-')) {
                return Http::response('Not Found', 404);
            }

            if (str_ends_with($url, '/robots.txt')) {
                return Http::response('User-agent: *', 200, ['Content-Type' => 'text/plain']);
            }

            return Http::response('Not Found', 404);
        });

        $data = (new WebsiteAnalyzer)->analyze('http://secure.test')->toArray();

        $this->assertSame('ready', $data['status']);
        $this->assertSame('https://secure.test', rtrim((string) $data['final_url'], '/'));
        $this->assertTrue($data['ssl']['enabled']);
        $this->assertTrue($data['https_redirect']);
        $this->assertTrue($data['redirected']);
    }

    public function test_https_url_without_scheme_change_is_not_https_redirect(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($url === 'https://secure.test' || $url === 'https://secure.test/') {
                return Http::response(
                    '<!DOCTYPE html><html><head><title>Secure</title></head><body></body></html>',
                    200,
                );
            }

            if (str_starts_with($url, 'https://www.secure.test')) {
                return Http::response('', 301, ['Location' => 'https://secure.test/']);
            }

            if (str_contains($url, '/set33-audit-404-')) {
                return Http::response('Not Found', 404);
            }

            if (str_ends_with($url, '/robots.txt')) {
                return Http::response('User-agent: *', 200);
            }

            return Http::response('Not Found', 404);
        });

        $data = (new WebsiteAnalyzer)->analyze('https://secure.test')->toArray();

        $this->assertSame('ready', $data['status']);
        $this->assertTrue($data['ssl']['enabled']);
        $this->assertFalse($data['https_redirect']);
    }
}
