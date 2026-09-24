<?php

namespace App\Actions\Site;

use App\Models\Site;
use App\Models\SiteEvent;

class CreateSiteEvent
{
    /**
     * @param  array{
     *     occurred_on: string,
     *     title: string,
     *     description?: string|null,
     *     url?: string|null
     * }  $data
     */
    public function handle(Site $site, array $data): SiteEvent
    {
        return $site->events()->create([
            'occurred_on' => $data['occurred_on'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? null,
        ]);
    }
}
