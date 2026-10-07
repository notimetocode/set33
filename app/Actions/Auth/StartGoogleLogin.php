<?php

namespace App\Actions\Auth;

use App\Support\Localization;

class StartGoogleLogin
{
    /**
     * @return array{authorize_url: string}
     */
    public function handle(?string $locale = null): array
    {
        $locale ??= Localization::defaultLocale();

        if (! Localization::isSupported($locale)) {
            $locale = Localization::defaultLocale();
        }

        $state = encrypt([
            'intent' => 'login',
            'locale' => $locale,
            'nonce' => bin2hex(random_bytes(16)),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', config('services.google.login_scopes', [])),
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return [
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$query,
        ];
    }
}
