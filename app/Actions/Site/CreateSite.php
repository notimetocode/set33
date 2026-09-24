<?php

namespace App\Actions\Site;

use App\Models\Site;
use App\Models\User;

class CreateSite
{
    /**
     * @param  array{name: string, url: string}  $data
     */
    public function handle(User $user, array $data): Site
    {
        return $user->sites()->create([
            'name' => $data['name'],
            'url' => $data['url'],
        ]);
    }
}
