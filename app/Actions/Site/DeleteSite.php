<?php

namespace App\Actions\Site;

use App\Models\Site;
use Illuminate\Support\Facades\Storage;

class DeleteSite
{
    public function handle(Site $site): void
    {
        Storage::disk('public')->deleteDirectory('sites/'.$site->id);

        $site->delete();
    }
}
