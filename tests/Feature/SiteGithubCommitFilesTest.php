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

class SiteGithubCommitFilesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_fetch_stores_commit_files(): void
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

        $commit = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'abc123def456',
            'message' => 'Fix homepage',
        ]);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/commits/abc123def456' => Http::response([
                'sha' => 'abc123def456',
                'html_url' => 'https://github.com/octocat/hello-world/commit/abc123def456',
                'commit' => [
                    'message' => 'Fix homepage',
                ],
                'stats' => [
                    'additions' => 12,
                    'deletions' => 3,
                    'total' => 15,
                ],
                'files' => [
                    [
                        'filename' => 'app/Http/Controllers/HomeController.php',
                        'status' => 'modified',
                        'additions' => 10,
                        'deletions' => 2,
                        'changes' => 12,
                        'patch' => "@@ -1,3 +1,5 @@\n+use App\\Foo;\n class HomeController",
                    ],
                    [
                        'filename' => 'resources/views/home.blade.php',
                        'status' => 'added',
                        'additions' => 2,
                        'deletions' => 1,
                        'changes' => 3,
                    ],
                ],
            ]),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/github-commits/{$commit->id}/files")
            ->assertOk()
            ->assertJsonPath('data.sha', 'abc123def456')
            ->assertJsonPath('data.stats.additions', 12)
            ->assertJsonPath('data.files.0.filename', 'app/Http/Controllers/HomeController.php')
            ->assertJsonPath('data.files.1.status', 'added');

        $commit->refresh();

        $this->assertNotNull($commit->files_fetched_at);
        $this->assertSame(12, $commit->stats['additions']);
        $this->assertCount(2, $commit->files);
    }

    public function test_show_returns_stored_files(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $commit = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'deadbeef01',
            'message' => 'Hello',
            'stats' => ['additions' => 1, 'deletions' => 0, 'total' => 1],
            'files' => [
                [
                    'filename' => 'README.md',
                    'status' => 'modified',
                    'additions' => 1,
                    'deletions' => 0,
                    'changes' => 1,
                    'previous_filename' => null,
                    'patch' => '+hello',
                ],
            ],
            'files_incomplete' => false,
            'files_fetched_at' => now(),
        ]);

        $this->getJson("/api/app/sites/{$site->id}/github-commits/{$commit->id}/files")
            ->assertOk()
            ->assertJsonPath('data.files.0.filename', 'README.md')
            ->assertJsonPath('data.stats.total', 1);
    }

    public function test_show_returns_404_when_files_not_fetched(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $commit = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'nofilesyet01',
        ]);

        $this->getJson("/api/app/sites/{$site->id}/github-commits/{$commit->id}/files")
            ->assertNotFound();
    }

    public function test_metrics_endpoint_includes_has_files_flag(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'deadbeef01',
            'message' => 'Hello world',
            'author_name' => 'Octocat',
            'author_date' => '2026-09-21 10:00:00',
            'files_fetched_at' => now(),
            'files' => [],
            'stats' => ['additions' => 0, 'deletions' => 0, 'total' => 0],
        ]);

        $response = $this->getJson("/api/app/sites/{$site->id}/metrics/github-commits?from=2026-09-01&to=2026-09-22")
            ->assertOk()
            ->assertJsonPath('data.0.has_files', true)
            ->assertJsonPath('data.0.sha', 'deadbeef01');

        $this->assertIsInt($response->json('data.0.id'));
    }

    public function test_sync_preserves_fetched_files_for_same_sha(): void
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

        SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'abc123def456',
            'message' => 'Old message',
            'author_date' => '2026-09-20 12:00:00',
            'files' => [
                [
                    'filename' => 'kept.php',
                    'status' => 'modified',
                    'additions' => 1,
                    'deletions' => 0,
                    'changes' => 1,
                    'previous_filename' => null,
                    'patch' => null,
                ],
            ],
            'stats' => ['additions' => 1, 'deletions' => 0, 'total' => 1],
            'files_fetched_at' => now()->subHour(),
        ]);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/commits*' => Http::response([
                [
                    'sha' => 'abc123def456',
                    'html_url' => 'https://github.com/octocat/hello-world/commit/abc123def456',
                    'commit' => [
                        'message' => 'New message',
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
            'metrics' => ['commits'],
        ])->assertOk();

        $commit = SiteGithubCommit::query()
            ->where('site_id', $site->id)
            ->where('sha', 'abc123def456')
            ->first();

        $this->assertNotNull($commit);
        $this->assertSame('New message', $commit->message);
        $this->assertNotNull($commit->files_fetched_at);
        $this->assertSame('kept.php', $commit->files[0]['filename']);
    }
}
