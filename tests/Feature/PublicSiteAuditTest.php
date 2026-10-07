<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicSiteAuditTest extends TestCase
{
    public function test_check_page_renders_with_url_query(): void
    {
        $this->get('/check?url=example.com')
            ->assertOk()
            ->assertSee('data-site-audit', false)
            ->assertSee('example.com', false)
            ->assertSee('site-audits', false)
            ->assertSee('site-audit-bootstrap', false);
    }

    public function test_public_api_returns_audit_report(): void
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
                        <title>Example</title>
                        <meta name="description" content="Demo">
                        <script src="https://www.googletagmanager.com/gtag/js?id=G-TEST123"></script>
                    </head>
                    <body>Hi</body>
                    </html>
                    HTML,
                    200,
                );
            }

            if (str_starts_with($url, 'https://www.example.test')) {
                return Http::response('', 301, ['Location' => 'https://example.test/']);
            }

            if (str_contains($url, '/set33-audit-404-')) {
                return Http::response('<a href="/">Home</a>', 404);
            }

            if ($url === 'https://example.test/robots.txt') {
                return Http::response("User-agent: *\nAllow: /\n", 200);
            }

            return Http::response('Not Found', 404);
        });

        $this->postJson('/api/public/site-audits', [
            'url' => 'example.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.title', 'Example')
            ->assertJsonPath('data.title_present', true)
            ->assertJsonPath('data.google_analytics.present', true)
            ->assertJsonPath('data.robots_txt.present', true)
            ->assertJsonPath('data.not_found_page.returns_404', true)
            ->assertJsonPath('data.ssl.valid', true);
    }

    public function test_public_api_rejects_localhost(): void
    {
        $this->postJson('/api/public/site-audits', [
            'url' => 'http://localhost',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_localized_check_page_is_available(): void
    {
        $html = $this->get('/ru/check?url=example.com')
            ->assertOk()
            ->assertSee('site-audit-bootstrap', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/"loading"\s*:\s*"/', $html);
        $this->assertTrue(
            str_contains($html, 'Формируем') || str_contains($html, '\u0424\u043e\u0440\u043c\u0438\u0440\u0443\u0435\u043c'),
        );
    }
}
