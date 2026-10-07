<?php

namespace Tests\Feature;

use App\Actions\Auth\CompleteGoogleLogin;
use App\Actions\Auth\FindOrCreateGoogleUser;
use App\Actions\Auth\IssueGoogleLoginCode;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthGoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/oauth/google/callback',
            'services.google.login_scopes' => ['openid', 'email', 'profile'],
        ]);
    }

    public function test_guest_can_start_google_login(): void
    {
        $response = $this->postJson('/api/app/auth/google/start', [
            'locale' => 'en',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['authorize_url']);

        $url = $response->json('authorize_url');
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('access_type=online', $url);
        $this->assertStringNotContainsString('prompt=consent', $url);
        $this->assertStringContainsString('state=', $url);

        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
        $payload = decrypt($query['state']);

        $this->assertSame('login', $payload['intent']);
        $this->assertSame('en', $payload['locale']);
    }

    public function test_google_login_start_requires_configuration(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->postJson('/api/app/auth/google/start')
            ->assertStatus(503)
            ->assertJsonPath('message', __('auth.google_not_configured'));
    }

    public function test_find_or_create_google_user_creates_new_user(): void
    {
        Event::fake([Registered::class]);

        $user = app(FindOrCreateGoogleUser::class)->handle([
            'google_id' => 'google-123',
            'email' => 'new@example.com',
            'name' => 'Google User',
        ], 'en');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'google_id' => 'google-123',
            'name' => 'Google User',
            'role' => UserRole::User->value,
            'locale' => 'en',
            'password' => null,
        ]);

        $this->assertNotNull($user->email_verified_at);
        Event::assertDispatched(Registered::class);
    }

    public function test_find_or_create_google_user_links_existing_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'taken@example.com',
            'google_id' => null,
        ]);

        $user = app(FindOrCreateGoogleUser::class)->handle([
            'google_id' => 'google-456',
            'email' => 'taken@example.com',
            'name' => 'Other Name',
        ], 'ru');

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('google-456', $user->fresh()->google_id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_find_or_create_google_user_reuses_google_id(): void
    {
        $existing = User::factory()->viaGoogle('google-789')->create([
            'email' => 'g@example.com',
        ]);

        $user = app(FindOrCreateGoogleUser::class)->handle([
            'google_id' => 'google-789',
            'email' => 'other@example.com',
            'name' => 'Ignored',
        ], 'en');

        $this->assertSame($existing->id, $user->id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_guest_can_exchange_google_login_code_for_token(): void
    {
        $user = User::factory()->viaGoogle()->create();
        $code = app(IssueGoogleLoginCode::class)->handle($user);

        $response = $this->postJson('/api/app/auth/google/exchange', [
            'code' => $code,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id);

        $this->withToken($response->json('token'))
            ->getJson('/api/app/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->postJson('/api/app/auth/google/exchange', [
            'code' => $code,
        ])->assertUnprocessable();
    }

    public function test_oauth_callback_login_intent_issues_code_and_redirects(): void
    {
        $user = User::factory()->viaGoogle()->create();
        $state = encrypt([
            'intent' => 'login',
            'locale' => 'en',
            'nonce' => 'abc',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $this->mock(CompleteGoogleLogin::class, function ($mock) use ($user, $state): void {
            $mock->shouldReceive('handle')
                ->once()
                ->with('auth-code', $state)
                ->andReturn([
                    'user' => $user,
                    'locale' => 'en',
                ]);
        });

        $response = $this->get('/oauth/google/callback?code=auth-code&state='.urlencode($state));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/app/login?google_code=', $location);

        parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);
        $this->assertNotEmpty($query['google_code'] ?? null);

        $this->postJson('/api/app/auth/google/exchange', [
            'code' => $query['google_code'],
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_oauth_callback_login_error_redirects_to_login(): void
    {
        $state = encrypt([
            'intent' => 'login',
            'locale' => 'en',
            'nonce' => 'abc',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $response = $this->get('/oauth/google/callback?error=access_denied&state='.urlencode($state));

        $response->assertRedirect();
        $this->assertStringContainsString(
            '/app/login?google=error',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_password_login_fails_for_google_only_user(): void
    {
        User::factory()->viaGoogle()->create([
            'email' => 'google-only@example.com',
        ]);

        $this->postJson('/api/app/auth/login', [
            'email' => 'google-only@example.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
