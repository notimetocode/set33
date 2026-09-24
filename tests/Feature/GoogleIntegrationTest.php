<?php

namespace Tests\Feature;

use App\Actions\Google\CompleteGoogleOAuth;
use App\Enums\SiteGoogleIntegrationStatus;
use App\Jobs\BackfillSiteGoogleMetricsJob;
use App\Models\GoogleConnection;
use App\Models\Site;
use App\Models\SiteAnalyticsDaily;
use App\Models\SiteGoogleIntegration;
use App\Models\SiteSearchConsoleDaily;
use App\Models\User;
use App\Services\Google\GoogleApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class GoogleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_oauth_start_returns_authorize_url_with_state(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/oauth/google/callback',
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $response = $this->postJson('/api/app/google/oauth/start', [
            'return_site_id' => $site->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['authorize_url']);

        $url = $response->json('authorize_url');
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('state=', $url);
    }

    public function test_oauth_callback_rejects_invalid_state(): void
    {
        $response = $this->get('/oauth/google/callback?code=abc&state=invalid');

        $response->assertRedirect();
        $this->assertStringContainsString(
            '/app/sites?google=error',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_oauth_state_decode_rejects_expired_payload(): void
    {
        $this->expectException(RuntimeException::class);

        $state = encrypt([
            'user_id' => 1,
            'return_site_id' => null,
            'nonce' => 'abc',
            'expires_at' => now()->subMinute()->timestamp,
        ]);

        app(CompleteGoogleOAuth::class)->decodeState($state);
    }

    public function test_user_can_save_site_google_integration_and_dispatch_backfill(): void
    {
        Queue::fake();

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        GoogleConnection::factory()->for($user)->create();

        $this->putJson("/api/app/sites/{$site->id}/google-integration", [
            'ga4_property_id' => 'properties/123456',
            'gsc_site_url' => 'https://example.com/',
        ])
            ->assertOk()
            ->assertJsonPath('data.ga4_property_id', 'properties/123456')
            ->assertJsonPath('data.gsc_site_url', 'https://example.com/')
            ->assertJsonPath('data.status', SiteGoogleIntegrationStatus::Active->value);

        Queue::assertPushed(BackfillSiteGoogleMetricsJob::class);
    }

    public function test_user_cannot_update_another_users_site_integration(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        GoogleConnection::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->putJson("/api/app/sites/{$site->id}/google-integration", [
            'ga4_property_id' => 'properties/1',
        ])->assertForbidden();
    }

    public function test_sync_upserts_daily_metrics_via_fake_google_client(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $connection = GoogleConnection::factory()->for($user)->create();
        $integration = SiteGoogleIntegration::factory()->create([
            'site_id' => $site->id,
            'google_connection_id' => $connection->id,
            'ga4_property_id' => 'properties/999',
            'gsc_site_url' => 'https://example.com/',
            'status' => SiteGoogleIntegrationStatus::Active,
        ]);

        $this->mock(GoogleApiClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('fetchAnalyticsDaily')
                ->once()
                ->andReturn([
                    [
                        'date' => '2026-09-20',
                        'sessions' => 10,
                        'total_users' => 8,
                        'new_users' => 3,
                        'screen_page_views' => 20,
                        'organic_sessions' => 4,
                        'organic_total_users' => 3,
                        'organic_new_users' => 1,
                    ],
                ]);

            $mock->shouldReceive('fetchSearchConsoleDaily')
                ->once()
                ->andReturn([
                    [
                        'date' => '2026-09-20',
                        'clicks' => 5,
                        'impressions' => 100,
                        'ctr' => 0.05,
                        'position' => 12.3,
                    ],
                ]);
        });

        $this->postJson("/api/app/sites/{$site->id}/google-integration/sync", [
            'from' => '2026-09-20',
            'to' => '2026-09-20',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', SiteGoogleIntegrationStatus::Active->value);

        $this->assertDatabaseHas('site_analytics_daily', [
            'site_id' => $site->id,
            'date' => '2026-09-20',
            'sessions' => 10,
            'total_users' => 8,
            'organic_sessions' => 4,
            'organic_total_users' => 3,
            'organic_new_users' => 1,
        ]);

        $this->assertDatabaseHas('site_search_console_daily', [
            'site_id' => $site->id,
            'date' => '2026-09-20',
            'clicks' => 5,
            'impressions' => 100,
        ]);

        $this->assertNotNull($integration->fresh()->last_synced_at);
    }

    public function test_metrics_endpoints_return_stored_rows(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        SiteAnalyticsDaily::factory()->create([
            'site_id' => $site->id,
            'date' => '2026-09-21',
            'sessions' => 42,
            'organic_sessions' => 15,
            'organic_total_users' => 12,
        ]);

        SiteSearchConsoleDaily::factory()->create([
            'site_id' => $site->id,
            'date' => '2026-09-21',
            'clicks' => 7,
        ]);

        $this->getJson("/api/app/sites/{$site->id}/metrics/analytics?from=2026-09-20&to=2026-09-22")
            ->assertOk()
            ->assertJsonPath('data.0.sessions', 42)
            ->assertJsonPath('data.0.organic_sessions', 15)
            ->assertJsonPath('data.0.organic_total_users', 12);

        $this->getJson("/api/app/sites/{$site->id}/metrics/search-console?from=2026-09-20&to=2026-09-22")
            ->assertOk()
            ->assertJsonPath('data.0.clicks', 7);
    }

    public function test_connection_show_and_disconnect(): void
    {
        Queue::fake();

        $user = $this->actingAsAppUser();
        $connection = GoogleConnection::factory()->for($user)->create([
            'google_account_email' => 'owner@example.com',
        ]);
        $site = Site::factory()->for($user)->create();
        SiteGoogleIntegration::factory()->create([
            'site_id' => $site->id,
            'google_connection_id' => $connection->id,
        ]);

        $this->getJson('/api/app/google/connection')
            ->assertOk()
            ->assertJsonPath('data.google_account_email', 'owner@example.com')
            ->assertJsonMissingPath('data.access_token');

        $this->mock(GoogleApiClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('revoke')->once();
        });

        $this->deleteJson('/api/app/google/connection')->assertNoContent();

        $this->assertDatabaseMissing('google_connections', ['id' => $connection->id]);
        $this->assertDatabaseMissing('site_google_integrations', ['site_id' => $site->id]);
    }
}
