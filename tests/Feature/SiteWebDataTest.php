<?php

namespace Tests\Feature;

use App\Enums\SiteWebDataStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteWebDataTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_creating_a_site_collects_homepage_metadata_favicon_and_robots(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Http::fake([
            'https://example.test' => Http::response(
                <<<'HTML'
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <title>Example Site</title>
                    <meta name="description" content="A demo description">
                    <meta name="keywords" content="demo, site">
                    <meta property="og:title" content="OG Example">
                    <meta property="og:description" content="OG description">
                    <meta property="og:image" content="/og.png">
                    <link rel="canonical" href="https://example.test/">
                    <link rel="icon" href="/icon.png" type="image/png">
                </head>
                <body>Hello</body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
            'https://example.test/icon.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            'https://example.test/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /admin\n",
                200,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        $user = $this->actingAsAppUser();

        $response = $this->postJson('/api/app/sites', [
            'name' => 'Example',
            'url' => 'https://example.test',
        ])->assertCreated();

        $siteId = $response->json('data.id');

        $this->assertDatabaseHas('sites', [
            'id' => $siteId,
            'web_data_status' => SiteWebDataStatus::Ready->value,
            'page_title' => 'Example Site',
            'meta_description' => 'A demo description',
            'meta_keywords' => 'demo, site',
            'og_title' => 'OG Example',
            'og_description' => 'OG description',
            'og_image_url' => 'https://example.test/og.png',
            'canonical_url' => 'https://example.test/',
            'html_lang' => 'en',
            'favicon_source_url' => 'https://example.test/icon.png',
        ]);

        $site = Site::query()->findOrFail($siteId);

        $this->assertNotNull($site->favicon_path);
        $this->assertTrue(Storage::disk('public')->exists($site->favicon_path));
        $this->assertSame("User-agent: *\nDisallow: /admin\n", $site->robots_txt);

        $this->getJson("/api/app/sites/{$siteId}")
            ->assertOk()
            ->assertJsonPath('data.page_title', 'Example Site')
            ->assertJsonPath('data.web_data_status', 'ready')
            ->assertJsonPath('data.robots_txt', "User-agent: *\nDisallow: /admin\n")
            ->assertJsonPath('data.favicon_url', Storage::disk('public')->url($site->favicon_path));
    }

    public function test_user_can_refresh_site_web_data(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();

        Http::fake([
            'https://refresh.test' => Http::response(
                '<html lang="ru"><head><title>Refreshed</title><meta name="description" content="New"></head></html>',
                200,
            ),
            'https://refresh.test/favicon.ico' => Http::response('not-an-image', 404),
            'https://refresh.test/robots.txt' => Http::response('User-agent: *', 200),
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create([
            'url' => 'https://refresh.test',
            'web_data_status' => SiteWebDataStatus::Failed,
            'web_data_error' => 'Old error',
        ]);

        $this->postJson("/api/app/sites/{$site->id}/web-data/refresh")
            ->assertAccepted()
            ->assertJsonPath('data.page_title', 'Refreshed')
            ->assertJsonPath('data.meta_description', 'New')
            ->assertJsonPath('data.web_data_status', 'ready')
            ->assertJsonPath('data.html_lang', 'ru');
    }

    public function test_foreign_user_cannot_refresh_site_web_data(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $this->actingAsAppUser();

        $this->postJson("/api/app/sites/{$site->id}/web-data/refresh")
            ->assertForbidden();
    }

    public function test_deleting_a_site_removes_stored_favicon_directory(): void
    {
        Storage::fake('public');

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $path = 'sites/'.$site->id.'/favicon.png';
        $site->forceFill(['favicon_path' => $path])->save();

        Storage::disk('public')->put($path, 'icon');

        $this->deleteJson("/api/app/sites/{$site->id}")
            ->assertNoContent();

        $this->assertFalse(Storage::disk('public')->exists($path));
    }
}
