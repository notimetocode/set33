<?php

namespace App\Actions\Google;

use App\Enums\GoogleConnectionStatus;
use App\Models\GoogleConnection;
use App\Models\User;
use App\Support\GoogleOAuthState;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CompleteGoogleOAuth
{
    /**
     * @return array{connection: GoogleConnection, return_site_id: int|null}
     */
    public function handle(string $code, string $state): array
    {
        $payload = $this->decodeState($state);
        $user = User::query()->findOrFail($payload['user_id']);
        $tokenPayload = $this->exchangeCodeForTokens($code);
        $email = $tokenPayload['email'] ?? $this->fetchEmail($tokenPayload['access_token']);

        $existing = GoogleConnection::query()->where('user_id', $user->id)->first();

        $connection = GoogleConnection::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'google_account_email' => $email ?: 'unknown@google',
                'access_token' => $tokenPayload['access_token'],
                'refresh_token' => $tokenPayload['refresh_token'] ?? $existing?->refresh_token,
                'expires_at' => now()->addSeconds((int) ($tokenPayload['expires_in'] ?? 3600)),
                'scopes' => $tokenPayload['scopes'],
                'status' => GoogleConnectionStatus::Active,
            ],
        );

        if (! filled($connection->refresh_token)) {
            $connection->forceFill([
                'status' => GoogleConnectionStatus::NeedsReauth,
            ])->save();
        }

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
        $payload = GoogleOAuthState::decrypt($state);
        GoogleOAuthState::assertNotExpired($payload);

        if (($payload['intent'] ?? null) === 'login') {
            throw new RuntimeException('Недействительный параметр state.');
        }

        if (! isset($payload['user_id'])) {
            throw new RuntimeException('Недействительный параметр state.');
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'return_site_id' => isset($payload['return_site_id']) ? (int) $payload['return_site_id'] : null,
            'nonce' => (string) $payload['nonce'],
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: list<string>, email: ?string}
     */
    private function exchangeCodeForTokens(string $code): array
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error']) || ! filled($token['access_token'] ?? null)) {
            throw new RuntimeException('Не удалось обменять код авторизации Google.');
        }

        $email = null;

        if (filled($token['id_token'] ?? null)) {
            try {
                $tokenInfo = $client->verifyIdToken($token['id_token']);
                if (is_array($tokenInfo)) {
                    $email = $tokenInfo['email'] ?? null;
                }
            } catch (Throwable) {
                // optional
            }
        }

        $scopes = [];

        if (isset($token['scope']) && is_string($token['scope'])) {
            $scopes = array_values(array_filter(explode(' ', $token['scope'])));
        }

        return [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_in' => (int) ($token['expires_in'] ?? 3600),
            'scopes' => $scopes !== [] ? $scopes : array_values(config('services.google.scopes', [])),
            'email' => $email,
        ];
    }

    private function fetchEmail(string $accessToken): ?string
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            return null;
        }

        return $response->json('email');
    }
}
