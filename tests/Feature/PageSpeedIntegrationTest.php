<?php

namespace Tests\Feature;

use App\Models\GoogleConnection;
use App\Models\Site;
use App\Models\SiteCruxSnapshot;
use App\Models\SitePageSpeedIntegration;
use App\Models\SitePageSpeedLabSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PageSpeedIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function pagespeedResponse(
        string $url = 'https://example.com/',
        int $score = 87,
        bool $withOrigin = true,
        bool $withUrl = true,
    ): array {
        $payload = [
            'id' => $url,
            'lighthouseResult' => [
                'categories' => [
                    'performance' => [
                        'score' => $score / 100,
                    ],
                ],
                'audits' => [
                    'largest-contentful-paint' => ['numericValue' => 2100],
                    'interaction-to-next-paint' => ['numericValue' => 120],
                    'cumulative-layout-shift' => ['numericValue' => 0.05],
                    'first-contentful-paint' => ['numericValue' => 1100],
                    'server-response-time' => ['numericValue' => 180],
                    'total-blocking-time' => ['numericValue' => 90],
                    'speed-index' => ['numericValue' => 2500],
                ],
            ],
        ];

        if ($withOrigin) {
            $payload['originLoadingExperience'] = [
                'id' => 'https://example.com',
                'metrics' => [
                    'LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 2200],
                    'INTERACTION_TO_NEXT_PAINT' => ['percentile' => 140],
                    'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 0.08],
                    'FIRST_CONTENTFUL_PAINT_MS' => ['percentile' => 1300],
                    'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => ['percentile' => 250],
                ],
            ];
        }

        if ($withUrl) {
            $payload['loadingExperience'] = [
                'id' => $url,
                'metrics' => [
                    'LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 1800],
                    'INTERACTION_TO_NEXT_PAINT' => ['percentile' => 80],
                    'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 0.02],
                    'FIRST_CONTENTFUL_PAINT_MS' => ['percentile' => 900],
                    'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => ['percentile' => 150],
                ],
            ];
        }

        return $payload;
    }

    public function test_site_integration_requires_google_connection(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $this->putJson("/api/app/sites/{$site->id}/pagespeed-integration", [
            'strategy' => 'mobile',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала подключите аккаунт Google.');
    }

    public function test_user_can_attach_pagespeed_to_site(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        GoogleConnection::factory()->for($user)->create();

        $this->putJson("/api/app/sites/{$site->id}/pagespeed-integration", [
            'strategy' => 'both',
        ])
            ->assertOk()
            ->assertJsonPath('data.strategy', 'both')
            ->assertJsonPath('data.is_configured', true);

        $this->assertDatabaseHas('site_pagespeed_integrations', [
            'site_id' => $site->id,
            'strategy' => 'both',
            'status' => 'active',
        ]);
    }

    public function test_sync_requires_site_integration(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $this->postJson("/api/app/sites/{$site->id}/pagespeed-integration/sync", [
            'metrics' => ['psi_lab'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала подключите PageSpeed Insights к сайту.');
    }

    public function test_quota_error_without_api_key_is_translated(): void
    {
        Http::preventStrayRequests();

        config([
            'services.pagespeed.psi_base_url' => 'https://www.googleapis.com',
            'services.pagespeed.api_key' => null,
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create([
            'url' => 'https://example.com/',
        ]);
        $connection = GoogleConnection::factory()->for($user)->create();
        SitePageSpeedIntegration::factory()->create([
            'site_id' => $site->id,
            'google_connection_id' => $connection->id,
            'strategy' => 'mobile',
        ]);

        Http::fake([
            'www.googleapis.com/pagespeedonline/v5/runPagespeed*' => Http::response([
                'error' => [
                    'message' => "Quota exceeded for quota metric 'Queries' and limit 'Queries per day' of service 'pagespeedonline.googleapis.com'",
                ],
            ], 429),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/pagespeed-integration/sync", [
            'metrics' => ['psi_lab'],
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Превышена дневная квота PageSpeed Insights. Укажите PAGESPEED_API_KEY в .env (ключ Google Cloud с включённым PageSpeed Insights API).',
            );
    }

    public function test_sync_stores_lab_and_crux_and_wipes_previous(): void
    {
        Http::preventStrayRequests();

        config([
            'services.pagespeed.psi_base_url' => 'https://www.googleapis.com',
            'services.pagespeed.api_key' => 'test-pagespeed-server-key',
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create([
            'url' => 'https://example.com/path',
        ]);
        $connection = GoogleConnection::factory()->for($user)->create();
        SitePageSpeedIntegration::factory()->create([
            'site_id' => $site->id,
            'google_connection_id' => $connection->id,
            'strategy' => 'mobile',
        ]);

        SitePageSpeedLabSnapshot::factory()->for($site)->create([
            'performance_score' => 10,
        ]);
        SiteCruxSnapshot::factory()->for($site)->create([
            'lcp_p75_ms' => 9999,
        ]);

        Http::fake([
            'www.googleapis.com/pagespeedonline/v5/runPagespeed*' => Http::response(
                $this->pagespeedResponse('https://example.com/path', 91),
            ),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/pagespeed-integration/sync", [
            'metrics' => ['psi_lab', 'crux_origin'],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'pagespeedonline/v5/runPagespeed')
                && $request['key'] === 'test-pagespeed-server-key';
        });

        $this->assertDatabaseCount('site_pagespeed_lab_snapshots', 1);
        $this->assertDatabaseHas('site_pagespeed_lab_snapshots', [
            'site_id' => $site->id,
            'strategy' => 'mobile',
            'performance_score' => 91,
            'lcp_ms' => 2100,
        ]);

        $this->assertDatabaseCount('site_crux_snapshots', 1);
        $this->assertDatabaseHas('site_crux_snapshots', [
            'site_id' => $site->id,
            'scope' => 'origin',
            'form_factor' => 'PHONE',
            'lcp_p75_ms' => 2200,
        ]);
    }

    public function test_sync_can_select_only_crux_url(): void
    {
        Http::preventStrayRequests();

        config([
            'services.pagespeed.psi_base_url' => 'https://www.googleapis.com',
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create([
            'url' => 'https://example.com/landing',
        ]);
        $connection = GoogleConnection::factory()->for($user)->create();
        SitePageSpeedIntegration::factory()->create([
            'site_id' => $site->id,
            'google_connection_id' => $connection->id,
            'strategy' => 'desktop',
        ]);

        SitePageSpeedLabSnapshot::factory()->for($site)->create();

        Http::fake([
            'www.googleapis.com/pagespeedonline/v5/runPagespeed*' => Http::response(
                $this->pagespeedResponse('https://example.com/landing', 80, withOrigin: false, withUrl: true),
            ),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/pagespeed-integration/sync", [
            'metrics' => ['crux_url'],
        ])->assertOk();

        $this->assertDatabaseCount('site_pagespeed_lab_snapshots', 1);
        $this->assertDatabaseCount('site_crux_snapshots', 1);
        $this->assertDatabaseHas('site_crux_snapshots', [
            'site_id' => $site->id,
            'scope' => 'url',
            'form_factor' => 'DESKTOP',
            'lcp_p75_ms' => 1800,
        ]);
    }

    public function test_metrics_endpoint_returns_lab_and_crux(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        SitePageSpeedLabSnapshot::factory()->for($site)->create([
            'strategy' => 'mobile',
            'performance_score' => 77,
        ]);
        SiteCruxSnapshot::factory()->for($site)->create([
            'scope' => 'origin',
            'form_factor' => 'PHONE',
            'lcp_p75_ms' => 2400,
        ]);

        $this->getJson("/api/app/sites/{$site->id}/metrics/pagespeed")
            ->assertOk()
            ->assertJsonPath('data.lab.0.performance_score', 77)
            ->assertJsonPath('data.crux.0.lcp_p75_ms', 2400);
    }

    public function test_user_cannot_access_another_users_site_pagespeed(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $this->actingAsAppUser();

        $this->putJson("/api/app/sites/{$site->id}/pagespeed-integration", [
            'strategy' => 'mobile',
        ])->assertForbidden();

        $this->getJson("/api/app/sites/{$site->id}/metrics/pagespeed")
            ->assertForbidden();
    }

    public function test_disconnect_site_integration_removes_row(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $connection = GoogleConnection::factory()->for($user)->create();
        SitePageSpeedIntegration::factory()->create([
            'site_id' => $site->id,
            'google_connection_id' => $connection->id,
        ]);

        $this->deleteJson("/api/app/sites/{$site->id}/pagespeed-integration")
            ->assertNoContent();

        $this->assertDatabaseMissing('site_pagespeed_integrations', [
            'site_id' => $site->id,
        ]);
        $this->assertDatabaseHas('google_connections', [
            'user_id' => $user->id,
        ]);
    }
}
