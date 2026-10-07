<?php

namespace App\Support;

use RuntimeException;
use Throwable;

class GoogleOAuthState
{
    /**
     * @return array<string, mixed>
     */
    public static function decrypt(string $state): array
    {
        try {
            /** @var mixed $payload */
            $payload = decrypt($state);
        } catch (Throwable) {
            throw new RuntimeException('Недействительный параметр state.');
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Недействительный параметр state.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function assertNotExpired(array $payload): void
    {
        if (! isset($payload['expires_at'], $payload['nonce'])) {
            throw new RuntimeException('Недействительный параметр state.');
        }

        if ((int) $payload['expires_at'] < now()->timestamp) {
            throw new RuntimeException('Срок действия ссылки авторизации истёк.');
        }
    }
}
