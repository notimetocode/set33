<?php

namespace Tests\Feature;

use App\Actions\Site\CollectSiteWebData;
use App\Enums\SiteWebDataStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollectSiteWebDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_collects_website_audit_and_persists_results_for_ai(): void
    {
        Storage::fake('public');
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
                        <title>Example Site</title>
                        <meta name="description" content="A demo description">
                        <meta property="og:title" content="OG Example">
                        <meta property="og:description" content="OG description">
                        <link rel="canonical" href="https://example.test/">
                        <link rel="icon" href="/icon.png" type="image/png">
                    </head>
                    <body>
                        <h1>Main heading</h1>
                        <p>Hello world content for the page analysis sample text.</p>
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
                return Http::response('<html><body><a href="/">Home</a></body></html>', 404);
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

            if ($url === 'https://example.test/icon.png') {
                return Http::response("\x89PNG\r\n\x1a\n".str_repeat('x', 32), 200, [
                    'Content-Type' => 'image/png',
                ]);
            }

            return Http::response('Not Found', 404);
        });

        $site = Site::factory()->for(User::factory())->create([
            'url' => 'https://example.test',
            'web_data_status' => SiteWebDataStatus::Pending,
        ]);

        $site = app(CollectSiteWebData::class)->handle($site);

        $this->assertSame(SiteWebDataStatus::Ready, $site->web_data_status);
        $this->assertSame('Example Site', $site->page_title);
        $this->assertSame('A demo description', $site->meta_description);
        $this->assertStringContainsString('User-agent:', (string) $site->robots_txt);
        $this->assertIsArray($site->site_audit);
        $this->assertSame('ready', $site->site_audit['status'] ?? null);
        $this->assertSame(200, $site->site_audit['http_status'] ?? null);
        $this->assertTrue($site->site_audit['sitemap']['present'] ?? false);
        $this->assertTrue($site->site_audit['ssl']['enabled'] ?? false);
        $this->assertNotNull($site->favicon_path);
        $this->assertTrue(Storage::disk('public')->exists($site->favicon_path));
    }

    public function test_stores_failed_audit_payload_when_homepage_is_unreachable(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://down.test/*' => Http::response('Gone', 500),
            'https://down.test' => Http::response('Gone', 500),
        ]);

        $site = Site::factory()->for(User::factory())->create([
            'url' => 'https://down.test',
            'web_data_status' => SiteWebDataStatus::Pending,
        ]);

        $site = app(CollectSiteWebData::class)->handle($site);

        $this->assertSame(SiteWebDataStatus::Failed, $site->web_data_status);
        $this->assertNotNull($site->web_data_error);
        $this->assertIsArray($site->site_audit);
        $this->assertSame('failed', $site->site_audit['status'] ?? null);
    }
}
