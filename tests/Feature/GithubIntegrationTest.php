<?php

namespace Tests\Feature;

use App\Actions\Github\CompleteGithubOAuth;
use App\Models\GithubConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class GithubIntegrationTest extends TestCase
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
            'services.github.client_id' => 'test-github-client-id',
            'services.github.client_secret' => 'test-github-client-secret',
            'services.github.redirect' => 'http://localhost/oauth/github/callback',
        ]);

        $this->actingAsAppUser();

        $response = $this->postJson('/api/app/github/oauth/start');

        $response->assertOk()
            ->assertJsonStructure(['authorize_url']);

        $url = $response->json('authorize_url');
        $this->assertStringContainsString('github.com/login/oauth/authorize', $url);
        $this->assertStringContainsString('client_id=test-github-client-id', $url);
        $this->assertStringContainsString('state=', $url);
    }

    public function test_oauth_start_returns_503_when_not_configured(): void
    {
        config([
            'services.github.client_id' => null,
            'services.github.client_secret' => null,
        ]);

        $this->actingAsAppUser();

        $this->postJson('/api/app/github/oauth/start')
            ->assertStatus(503)
            ->assertJsonPath('message', 'OAuth GitHub не настроен на сервере.');
    }

    public function test_oauth_callback_rejects_invalid_state(): void
    {
        $response = $this->get('/oauth/github/callback?code=abc&state=invalid');

        $response->assertRedirect();
        $this->assertStringContainsString(
            '/app/sites?github=error',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_oauth_state_decode_rejects_expired_payload(): void
    {
        $this->expectException(RuntimeException::class);

        $state = encrypt([
            'user_id' => 1,
            'nonce' => 'abc',
            'expires_at' => now()->subMinute()->timestamp,
        ]);

        app(CompleteGithubOAuth::class)->decodeState($state);
    }

    public function test_oauth_callback_creates_connection(): void
    {
        Http::preventStrayRequests();

        config([
            'services.github.client_id' => 'test-github-client-id',
            'services.github.client_secret' => 'test-github-client-secret',
            'services.github.redirect' => 'http://localhost/oauth/github/callback',
            'services.github.api_base_url' => 'https://api.github.com',
        ]);

        $user = User::factory()->create();

        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'gho_test_token',
                'token_type' => 'bearer',
                'scope' => 'read:user,user:email,repo',
            ]),
            'api.github.com/user' => Http::response([
                'id' => 42,
                'login' => 'octocat',
                'email' => 'octocat@example.com',
            ]),
        ]);

        $state = encrypt([
            'user_id' => $user->id,
            'nonce' => 'abc',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $response = $this->get('/oauth/github/callback?code=oauth-code&state='.urlencode($state));

        $response->assertRedirect('/app/sites?github=connected');

        $this->assertDatabaseHas('github_connections', [
            'user_id' => $user->id,
            'github_user_id' => 42,
            'github_login' => 'octocat',
            'github_account_email' => 'octocat@example.com',
            'status' => 'active',
        ]);
    }

    public function test_user_can_view_github_connection(): void
    {
        $user = $this->actingAsAppUser();
        GithubConnection::factory()->for($user)->create([
            'github_login' => 'octocat',
        ]);

        $this->getJson('/api/app/github/connection')
            ->assertOk()
            ->assertJsonPath('data.github_login', 'octocat')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_repositories_require_connection(): void
    {
        $this->actingAsAppUser();

        $this->getJson('/api/app/github/repositories')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала подключите аккаунт GitHub.');
    }

    public function test_user_can_list_repositories_via_fake_github_api(): void
    {
        Http::preventStrayRequests();

        config([
            'services.github.api_base_url' => 'https://api.github.com',
        ]);

        $user = $this->actingAsAppUser();
        GithubConnection::factory()->for($user)->withoutExpiry()->create([
            'access_token' => 'gho_test_token',
        ]);

        Http::fake([
            'api.github.com/user/repos*' => Http::response([
                [
                    'id' => 1,
                    'name' => 'hello-world',
                    'full_name' => 'octocat/hello-world',
                    'private' => false,
                    'html_url' => 'https://github.com/octocat/hello-world',
                    'description' => 'Demo',
                    'default_branch' => 'main',
                    'updated_at' => '2026-01-01T00:00:00Z',
                ],
            ]),
        ]);

        $this->getJson('/api/app/github/repositories')
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'octocat/hello-world')
            ->assertJsonPath('data.0.name', 'hello-world');
    }

    public function test_commits_require_connection(): void
    {
        $this->actingAsAppUser();

        $this->getJson('/api/app/github/repositories/octocat/hello-world/commits')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала подключите аккаунт GitHub.');
    }

    public function test_user_can_list_commits_via_fake_github_api(): void
    {
        Http::preventStrayRequests();

        config([
            'services.github.api_base_url' => 'https://api.github.com',
        ]);

        $user = $this->actingAsAppUser();
        GithubConnection::factory()->for($user)->withoutExpiry()->create([
            'access_token' => 'gho_test_token',
        ]);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/commits*' => Http::response([
                [
                    'sha' => 'abc123',
                    'html_url' => 'https://github.com/octocat/hello-world/commit/abc123',
                    'commit' => [
                        'message' => 'Initial commit',
                        'author' => [
                            'name' => 'Octocat',
                            'email' => 'octocat@example.com',
                            'date' => '2026-01-02T12:00:00Z',
                        ],
                        'committer' => [
                            'name' => 'GitHub',
                            'email' => 'noreply@github.com',
                            'date' => '2026-01-02T12:00:00Z',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/app/github/repositories/octocat/hello-world/commits')
            ->assertOk()
            ->assertJsonPath('data.0.sha', 'abc123')
            ->assertJsonPath('data.0.message', 'Initial commit')
            ->assertJsonPath('data.0.author_name', 'Octocat');
    }

    public function test_branches_require_connection(): void
    {
        $this->actingAsAppUser();

        $this->getJson('/api/app/github/repositories/octocat/hello-world/branches')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала подключите аккаунт GitHub.');
    }

    public function test_user_can_list_branches_via_fake_github_api(): void
    {
        Http::preventStrayRequests();

        config([
            'services.github.api_base_url' => 'https://api.github.com',
        ]);

        $user = $this->actingAsAppUser();
        GithubConnection::factory()->for($user)->withoutExpiry()->create([
            'access_token' => 'gho_test_token',
        ]);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/branches*' => Http::response([
                [
                    'name' => 'main',
                    'protected' => true,
                    'commit' => [
                        'sha' => 'abc123',
                        'url' => 'https://api.github.com/repos/octocat/hello-world/commits/abc123',
                    ],
                ],
                [
                    'name' => 'develop',
                    'protected' => false,
                    'commit' => [
                        'sha' => 'def456',
                        'url' => 'https://api.github.com/repos/octocat/hello-world/commits/def456',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/app/github/repositories/octocat/hello-world/branches')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'main')
            ->assertJsonPath('data.0.protected', true)
            ->assertJsonPath('data.0.commit_sha', 'abc123')
            ->assertJsonPath('data.1.name', 'develop');
    }

    public function test_user_can_disconnect_github(): void
    {
        Http::preventStrayRequests();

        config([
            'services.github.client_id' => 'test-github-client-id',
            'services.github.client_secret' => 'test-github-client-secret',
        ]);

        $user = $this->actingAsAppUser();
        $connection = GithubConnection::factory()->for($user)->withoutExpiry()->create();

        Http::fake([
            'api.github.com/applications/*/token' => Http::response(null, 204),
        ]);

        $this->deleteJson('/api/app/github/connection')
            ->assertNoContent();

        $this->assertDatabaseMissing('github_connections', [
            'id' => $connection->id,
        ]);
    }
}
