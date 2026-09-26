<?php

namespace App\Actions\PageSpeed;

use App\Enums\PageSpeedStrategy;
use App\Enums\SitePageSpeedIntegrationStatus;
use App\Models\Site;
use App\Models\SitePageSpeedIntegration;
use App\Models\User;
use InvalidArgumentException;

class UpsertSitePageSpeedIntegration
{
    /**
     * @param  array{strategy?: string}  $data
     */
    public function handle(User $user, Site $site, array $data): SitePageSpeedIntegration
    {
        $connection = $user->googleConnection;

        if ($connection === null) {
            throw new InvalidArgumentException('Сначала подключите аккаунт Google.');
        }

        if ($connection->needsReauth()) {
            throw new InvalidArgumentException('Нужна повторная авторизация Google.');
        }

        $strategy = PageSpeedStrategy::tryFrom((string) ($data['strategy'] ?? PageSpeedStrategy::Mobile->value))
            ?? PageSpeedStrategy::Mobile;

        $integration = SitePageSpeedIntegration::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'google_connection_id' => $connection->id,
                'strategy' => $strategy,
                'status' => SitePageSpeedIntegrationStatus::Active,
                'last_error' => null,
            ],
        );

        return $integration->refresh()->load('googleConnection');
    }
}
