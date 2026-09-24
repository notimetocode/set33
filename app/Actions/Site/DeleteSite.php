<?php

namespace App\Actions\Site;

use App\Models\Site;

class DeleteSite
{
    public function handle(Site $site): void
    {
        $site->delete();
    }
}
