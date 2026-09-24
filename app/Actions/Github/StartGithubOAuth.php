<?php

namespace App\Actions\Github;

use App\Models\User;

class StartGithubOAuth
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
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => config('services.github.redirect'),
            'scope' => implode(' ', config('services.github.scopes', [])),
            'state' => $state,
            'allow_signup' => 'false',
        ]);

        return [
            'authorize_url' => 'https://github.com/login/oauth/authorize?'.$query,
        ];
    }
}
