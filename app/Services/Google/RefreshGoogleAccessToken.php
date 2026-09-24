<?php

namespace App\Services\Google;

use App\Enums\GoogleConnectionStatus;
use App\Models\GoogleConnection;
use Google\Client as GoogleClient;
use RuntimeException;
use Throwable;

class RefreshGoogleAccessToken
{
    public function handle(GoogleConnection $connection): GoogleConnection
    {
        if (! $connection->accessTokenExpired()) {
            return $connection;
        }

        if (! filled($connection->refresh_token)) {
            $connection->forceFill([
                'status' => GoogleConnectionStatus::NeedsReauth,
            ])->save();

            throw new RuntimeException('Требуется повторная авторизация Google.');
        }

        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));
        try {
            $client->refreshToken($connection->refresh_token);
        } catch (Throwable $e) {
            $connection->forceFill([
                'status' => GoogleConnectionStatus::NeedsReauth,
            ])->save();

            throw new RuntimeException('Требуется повторная авторизация Google.', 0, $e);
        }

        $token = $client->getAccessToken();

        if (! is_array($token) || ! filled($token['access_token'] ?? null) || isset($token['error'])) {
            $connection->forceFill([
                'status' => GoogleConnectionStatus::NeedsReauth,
            ])->save();

            throw new RuntimeException('Не удалось обновить токен Google.');
        }

        $connection->forceFill([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'status' => GoogleConnectionStatus::Active,
        ])->save();

        return $connection->refresh();
    }
}
