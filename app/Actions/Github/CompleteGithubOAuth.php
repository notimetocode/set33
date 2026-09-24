<?php

namespace App\Actions\Github;

use App\Enums\GithubConnectionStatus;
use App\Models\GithubConnection;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CompleteGithubOAuth
{
    /**
     * @return array{connection: GithubConnection, return_site_id: int|null}
     */
    public function handle(string $code, string $state): array
    {
        $payload = $this->decodeState($state);
        $user = User::query()->findOrFail($payload['user_id']);
        $tokenPayload = $this->exchangeCodeForTokens($code);
        $profile = $this->fetchAuthenticatedUser($tokenPayload['access_token']);

        $connection = GithubConnection::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'github_user_id' => $profile['id'],
                'github_login' => $profile['login'],
                'github_account_email' => $profile['email'],
                'access_token' => $tokenPayload['access_token'],
                'refresh_token' => $tokenPayload['refresh_token'],
                'expires_at' => $tokenPayload['expires_in'] !== null
                    ? now()->addSeconds($tokenPayload['expires_in'])
                    : null,
                'scopes' => $tokenPayload['scopes'],
                'status' => GithubConnectionStatus::Active,
            ],
        );

        return [
            'connection' => $connection->refresh(),
            'return_site_id' => $payload['return_site_id'],
        ];
    }

    /**
     * @return array{user_id: int, return_site_id: int|null, nonce: string, expires_at: int}
     */
    public function decodeState(string $state): array
    {
        try {
            /** @var array{user_id?: mixed, return_site_id?: mixed, nonce?: mixed, expires_at?: mixed} $payload */
            $payload = decrypt($state);
        } catch (Throwable) {
            throw new RuntimeException('Недействительный параметр state.');
        }

        if (! isset($payload['user_id'], $payload['expires_at'], $payload['nonce'])) {
            throw new RuntimeException('Недействительный параметр state.');
        }

        if ((int) $payload['expires_at'] < now()->timestamp) {
            throw new RuntimeException('Срок действия ссылки авторизации истёк.');
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'return_site_id' => isset($payload['return_site_id']) ? (int) $payload['return_site_id'] : null,
            'nonce' => (string) $payload['nonce'],
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int, scopes: list<string>}
     */
    private function exchangeCodeForTokens(string $code): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(15)
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'code' => $code,
                'redirect_uri' => config('services.github.redirect'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Не удалось обменять код авторизации GitHub.');
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (isset($body['error']) || ! filled($body['access_token'] ?? null)) {
            throw new RuntimeException('Не удалось обменять код авторизации GitHub.');
        }

        $scopes = [];

        if (isset($body['scope']) && is_string($body['scope'])) {
            $scopes = array_values(array_filter(preg_split('/[\s,]+/', $body['scope']) ?: []));
        }

        return [
            'access_token' => (string) $body['access_token'],
            'refresh_token' => isset($body['refresh_token']) ? (string) $body['refresh_token'] : null,
            'expires_in' => isset($body['expires_in']) ? (int) $body['expires_in'] : null,
            'scopes' => $scopes !== [] ? $scopes : array_values(config('services.github.scopes', [])),
        ];
    }

    /**
     * @return array{id: int, login: string, email: ?string}
     */
    private function fetchAuthenticatedUser(string $accessToken): array
    {
        $baseUrl = rtrim((string) config('services.github.api_base_url'), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($accessToken)
                ->accept('application/vnd.github+json')
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->connectTimeout(3)
                ->timeout(15)
                ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false)
                ->get('/user');
        } catch (ConnectionException) {
            throw new RuntimeException('Не удалось получить профиль GitHub.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Не удалось получить профиль GitHub.');
        }

        $id = (int) $response->json('id');
        $login = (string) $response->json('login');

        if ($id < 1 || $login === '') {
            throw new RuntimeException('Некорректный ответ профиля GitHub.');
        }

        $email = $response->json('email');

        if (! filled($email)) {
            $email = $this->fetchPrimaryEmail($accessToken);
        }

        return [
            'id' => $id,
            'login' => $login,
            'email' => filled($email) ? (string) $email : null,
        ];
    }

    private function fetchPrimaryEmail(string $accessToken): ?string
    {
        $baseUrl = rtrim((string) config('services.github.api_base_url'), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($accessToken)
                ->accept('application/vnd.github+json')
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->connectTimeout(3)
                ->timeout(15)
                ->get('/user/emails');
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var list<array{email?: string, primary?: bool, verified?: bool}> $emails */
        $emails = $response->json() ?? [];

        foreach ($emails as $entry) {
            if (($entry['primary'] ?? false) && filled($entry['email'] ?? null)) {
                return (string) $entry['email'];
            }
        }

        foreach ($emails as $entry) {
            if (filled($entry['email'] ?? null)) {
                return (string) $entry['email'];
            }
        }

        return null;
    }
}
