<?php

namespace App\Actions\Site;

use App\Enums\SiteWebDataStatus;
use App\Models\Site;
use App\Models\User;

class CreateSite
{
    public function __construct(
        private CollectSiteWebData $collectSiteWebData,
    ) {}

    /**
     * @param  array{name: string, url: string}  $data
     */
    public function handle(User $user, array $data): Site
    {
        $site = $user->sites()->create([
            'name' => $data['name'],
            'url' => $data['url'],
            'web_data_status' => SiteWebDataStatus::Pending,
        ]);

        return $this->collectSiteWebData->handle($site);
    }
}
