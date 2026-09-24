<?php

namespace Tests\Feature;

use App\Models\GithubConnection;
use App\Models\Site;
use App\Models\SiteGithubIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteGithubIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_oauth_start_accepts_return_site_id(): void
    {
        config([
            'services.github.client_id' => 'test-github-client-id',
            'services.github.client_secret' => 'test-github-client-secret',
            'services.github.redirect' => 'http://localhost/oauth/github/callback',
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $response = $this->postJson('/api/app/github/oauth/start', [
            'return_site_id' => $site->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['authorize_url']);
    }

    public function test_oauth_callback_redirects_to_site_when_return_site_id_present(): void
    {
        Http::preventStrayRequests();

        config([
            'services.github.client_id' => 'test-github-client-id',
            'services.github.client_secret' => 'test-github-client-secret',
            'services.github.redirect' => 'http://localhost/oauth/github/callback',
            'services.github.api_base_url' => 'https://api.github.com',
        ]);

        $user = User::factory()->create();
        $site = Site::factory()->for($user)->create();

        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'gho_test_token',
                'token_type' => 'bearer',
                'scope' => 'read:user user:email repo',
            ]),
            'api.github.com/user' => Http::response([
                'id' => 42,
                'login' => 'octocat',
                'email' => 'octocat@example.com',
            ]),
        ]);

        $state = encrypt([
            'user_id' => $user->id,
            'return_site_id' => $site->id,
            'nonce' => 'abc',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $this->get('/oauth/github/callback?code=oauth-code&state='.urlencode($state))
            ->assertRedirect('/app/sites/'.$site->id.'?github=connected');
    }

    public function test_user_can_save_site_github_integration(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        GithubConnection::factory()->for($user)->create();

        $this->putJson("/api/app/sites/{$site->id}/github-integration", [
            'repository_full_name' => 'octocat/hello-world',
            'repository_id' => 123,
            'default_branch' => 'develop',
        ])
            ->assertOk()
            ->assertJsonPath('data.repository_full_name', 'octocat/hello-world')
            ->assertJsonPath('data.repository_owner', 'octocat')
            ->assertJsonPath('data.repository_name', 'hello-world')
            ->assertJsonPath('data.default_branch', 'develop')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_configured', true);

        $this->assertDatabaseHas('site_github_integrations', [
            'site_id' => $site->id,
            'repository_full_name' => 'octocat/hello-world',
            'default_branch' => 'develop',
        ]);
    }

    public function test_save_requires_branch(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        GithubConnection::factory()->for($user)->create();

        $this->putJson("/api/app/sites/{$site->id}/github-integration", [
            'repository_full_name' => 'octocat/hello-world',
            'repository_id' => 123,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['default_branch']);
    }

    public function test_user_cannot_update_another_users_site_github_integration(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        GithubConnection::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->putJson("/api/app/sites/{$site->id}/github-integration", [
            'repository_full_name' => 'octocat/hello-world',
        ])->assertForbidden();
    }

    public function test_save_requires_github_connection(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $this->putJson("/api/app/sites/{$site->id}/github-integration", [
            'repository_full_name' => 'octocat/hello-world',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала подключите аккаунт GitHub.');
    }

    public function test_site_resource_includes_github_integration(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $connection = GithubConnection::factory()->for($user)->create();
        SiteGithubIntegration::factory()->for($site)->create([
            'github_connection_id' => $connection->id,
            'repository_full_name' => 'octocat/hello-world',
            'repository_owner' => 'octocat',
            'repository_name' => 'hello-world',
        ]);

        $this->getJson("/api/app/sites/{$site->id}")
            ->assertOk()
            ->assertJsonPath('data.github_integration.repository_full_name', 'octocat/hello-world');
    }

    public function test_user_can_delete_site_github_integration(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $connection = GithubConnection::factory()->for($user)->create();
        $integration = SiteGithubIntegration::factory()->for($site)->create([
            'github_connection_id' => $connection->id,
        ]);

        $this->deleteJson("/api/app/sites/{$site->id}/github-integration")
            ->assertNoContent();

        $this->assertDatabaseMissing('site_github_integrations', [
            'id' => $integration->id,
        ]);
    }

    public function test_disconnect_github_removes_site_integrations(): void
    {
        Http::preventStrayRequests();

        config([
            'services.github.client_id' => 'test-github-client-id',
            'services.github.client_secret' => 'test-github-client-secret',
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $connection = GithubConnection::factory()->for($user)->withoutExpiry()->create();
        SiteGithubIntegration::factory()->for($site)->create([
            'github_connection_id' => $connection->id,
        ]);

        Http::fake([
            'api.github.com/applications/*/token' => Http::response(null, 204),
        ]);

        $this->deleteJson('/api/app/github/connection')
            ->assertNoContent();

        $this->assertDatabaseMissing('github_connections', ['id' => $connection->id]);
        $this->assertDatabaseMissing('site_github_integrations', ['site_id' => $site->id]);
    }
}
