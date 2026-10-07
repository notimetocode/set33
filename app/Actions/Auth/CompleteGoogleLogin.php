<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\GoogleOAuthState;
use App\Support\Localization;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CompleteGoogleLogin
{
    public function __construct(
        private FindOrCreateGoogleUser $findOrCreateGoogleUser,
    ) {}

    /**
     * @return array{user: User, locale: string}
     */
    public function handle(string $code, string $state): array
    {
        $payload = $this->decodeState($state);
        $profile = $this->fetchGoogleProfile($code);

        $user = $this->findOrCreateGoogleUser->handle($profile, $payload['locale']);

        return [
            'user' => $user,
            'locale' => $payload['locale'],
        ];
    }

    /**
     * @return array{intent: string, locale: string, nonce: string, expires_at: int}
     */
    public function decodeState(string $state): array
    {
        $payload = GoogleOAuthState::decrypt($state);
        GoogleOAuthState::assertNotExpired($payload);

        if (($payload['intent'] ?? null) !== 'login') {
            throw new RuntimeException('Недействительный параметр state.');
        }

        $locale = isset($payload['locale']) && is_string($payload['locale'])
            ? $payload['locale']
            : Localization::defaultLocale();

        if (! Localization::isSupported($locale)) {
            $locale = Localization::defaultLocale();
        }

        return [
            'intent' => 'login',
            'locale' => $locale,
            'nonce' => (string) $payload['nonce'],
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    /**
     * @return array{google_id: string, email: string, name: string}
     */
    private function fetchGoogleProfile(string $code): array
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error']) || ! filled($token['access_token'] ?? null)) {
            throw new RuntimeException('Не удалось обменять код авторизации Google.');
        }

        $googleId = null;
        $email = null;
        $name = null;
        $emailVerified = false;

        if (filled($token['id_token'] ?? null)) {
            try {
                $tokenInfo = $client->verifyIdToken($token['id_token']);
                if (is_array($tokenInfo)) {
                    $googleId = isset($tokenInfo['sub']) ? (string) $tokenInfo['sub'] : null;
                    $email = isset($tokenInfo['email']) ? (string) $tokenInfo['email'] : null;
                    $name = isset($tokenInfo['name']) ? (string) $tokenInfo['name'] : null;
                    $emailVerified = (bool) ($tokenInfo['email_verified'] ?? false);
                }
            } catch (Throwable) {
                // fall through to userinfo
            }
        }

        if ($googleId === null || $email === null || $name === null) {
            $userInfo = $this->fetchUserInfo((string) $token['access_token']);
            $googleId ??= $userInfo['id'] ?? null;
            $email ??= $userInfo['email'] ?? null;
            $name ??= $userInfo['name'] ?? null;
            $emailVerified = $emailVerified || (bool) ($userInfo['verified_email'] ?? false);
        }

        if (! filled($googleId) || ! filled($email)) {
            throw new RuntimeException('Не удалось получить профиль Google.');
        }

        if (! $emailVerified) {
            throw new RuntimeException('Email Google не подтверждён.');
        }

        $name = filled($name) ? trim((string) $name) : strtok((string) $email, '@');

        return [
            'google_id' => (string) $googleId,
            'email' => mb_strtolower(trim((string) $email)),
            'name' => (string) $name,
        ];
    }

    /**
     * @return array{id?: string, email?: string, name?: string, verified_email?: bool}
     */
    private function fetchUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            return [];
        }

        /** @var array{id?: string, email?: string, name?: string, verified_email?: bool} $json */
        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
