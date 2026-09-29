<?php

namespace App\Actions\PageSpeed;

use App\Enums\PageSpeedStrategy;
use App\Enums\SitePageSpeedIntegrationStatus;
use App\Models\Site;
use App\Models\SitePageSpeedIntegration;
use App\Models\User;
use App\Services\PageSpeed\PageSpeedApiClient;
use InvalidArgumentException;

class UpsertSitePageSpeedIntegration
{
    public function __construct(
        private readonly PageSpeedApiClient $pageSpeedApiClient,
    ) {}

    /**
     * @param  array{strategy?: string, page_urls?: list<string>|null}  $data
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

        $pageUrls = $this->normalizePageUrls(
            is_array($data['page_urls'] ?? null) ? $data['page_urls'] : [],
            (string) $site->url,
        );

        $integration = SitePageSpeedIntegration::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'google_connection_id' => $connection->id,
                'strategy' => $strategy,
                'page_urls' => $pageUrls,
                'status' => SitePageSpeedIntegrationStatus::Active,
                'last_error' => null,
            ],
        );

        return $integration->refresh()->load('googleConnection');
    }

    /**
     * @param  list<mixed>  $pageUrls
     * @return list<string>|null
     */
    private function normalizePageUrls(array $pageUrls, string $siteUrl): ?array
    {
        $resolved = [];

        foreach ($pageUrls as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);

            if ($trimmed === '') {
                continue;
            }

            $normalized = $this->pageSpeedApiClient->normalizePageUrl($trimmed, $siteUrl);

            if ($normalized === null) {
                throw new InvalidArgumentException("Некорректный URL страницы: {$trimmed}");
            }

            if (in_array($normalized, $resolved, true)) {
                continue;
            }

            $resolved[] = $normalized;

            if (count($resolved) > 10) {
                throw new InvalidArgumentException('Можно указать не больше 10 URL страниц.');
            }
        }

        return $resolved === [] ? null : $resolved;
    }
}
