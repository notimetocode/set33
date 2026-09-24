<?php

namespace App\Actions\Site;

use App\Models\SiteEvent;

class UpdateSiteEvent
{
    /**
     * @param  array{
     *     occurred_on: string,
     *     title: string,
     *     description?: string|null,
     *     url?: string|null
     * }  $data
     */
    public function handle(SiteEvent $event, array $data): SiteEvent
    {
        $event->update([
            'occurred_on' => $data['occurred_on'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? null,
        ]);

        return $event->refresh();
    }
}
