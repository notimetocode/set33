<?php

namespace App\Actions\Site;

use App\Models\Site;

class UpdateSite
{
    /**
     * @param  array{name: string, url: string}  $data
     */
    public function handle(Site $site, array $data): Site
    {
        $site->fill([
            'name' => $data['name'],
            'url' => $data['url'],
        ])->save();

        return $site->refresh();
    }
}
