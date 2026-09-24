<?php

namespace App\Actions\Google;

use App\Models\User;

class StartGoogleOAuth
{
    /**
     * @return array{authorize_url: string}
     */
    public function handle(User $user, ?int $returnSiteId = null): array
    {
        $state = encrypt([
            'user_id' => $user->id,
            'return_site_id' => $returnSiteId,
            'nonce' => bin2hex(random_bytes(16)),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', config('services.google.scopes', [])),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return [
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$query,
        ];
    }
}
