<?php

namespace App\Actions\Site;

use App\Models\SiteDocument;

class DeleteSiteDocument
{
    public function handle(SiteDocument $document): void
    {
        $document->delete();
    }
}
