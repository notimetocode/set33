<?php

namespace Tests\Feature;

use App\Models\GithubConnection;
use App\Models\Site;
use App\Models\SiteGithubCommit;
use App\Models\SiteGithubIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteGithubCommitsSyncTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_sync_upserts_commits_for_period(): void
    {
        Http::preventStrayRequests();

        config([
            'services.github.api_base_url' => 'https://api.github.com',
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $connection = GithubConnection::factory()->for($user)->withoutExpiry()->create([
            'access_token' => 'gho_test_token',
        ]);
        SiteGithubIntegration::factory()->for($site)->create([
            'github_connection_id' => $connection->id,
            'repository_owner' => 'octocat',
            'repository_name' => 'hello-world',
            'repository_full_name' => 'octocat/hello-world',
            'default_branch' => 'main',
        ]);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/commits*' => Http::response([
                [
                    'sha' => 'abc123def456',
                    'html_url' => 'https://github.com/octocat/hello-world/commit/abc123def456',
                    'commit' => [
                        'message' => "Fix homepage\n\nDetails",
                        'author' => [
                            'name' => 'Octocat',
                            'email' => 'octocat@example.com',
                            'date' => '2026-09-20T12:00:00Z',
                        ],
                        'committer' => [
                            'name' => 'GitHub',
                            'email' => 'noreply@github.com',
                            'date' => '2026-09-20T12:00:00Z',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/github-integration/sync", [
            'from' => '2026-09-20',
            'to' => '2026-09-20',
        ])
            ->assertOk()
            ->assertJsonPath('data.repository_full_name', 'octocat/hello-world')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('site_github_commits', [
            'site_id' => $site->id,
            'sha' => 'abc123def456',
            'author_name' => 'Octocat',
        ]);

        $this->assertNotNull($site->githubIntegration()->first()?->last_synced_at);
    }

    public function test_sync_requires_configured_repository(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $this->postJson("/api/app/sites/{$site->id}/github-integration/sync", [
            'from' => '2026-09-01',
            'to' => '2026-09-20',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала привяжите репозиторий и ветку GitHub к сайту.');
    }

    public function test_metrics_endpoint_returns_stored_commits(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'deadbeef01',
            'message' => 'Hello world',
            'author_name' => 'Octocat',
            'author_date' => '2026-09-21 10:00:00',
        ]);

        $this->getJson("/api/app/sites/{$site->id}/metrics/github-commits?from=2026-09-01&to=2026-09-22")
            ->assertOk()
            ->assertJsonPath('data.0.sha', 'deadbeef01')
            ->assertJsonPath('data.0.short_sha', 'deadbee')
            ->assertJsonPath('data.0.author_name', 'Octocat');
    }
}
