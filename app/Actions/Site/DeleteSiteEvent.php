<?php

namespace App\Actions\Site;

use App\Models\SiteEvent;

class DeleteSiteEvent
{
    public function handle(SiteEvent $event): void
    {
        $event->delete();
    }
}
