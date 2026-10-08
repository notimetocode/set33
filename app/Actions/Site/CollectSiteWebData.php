<?php

namespace App\Actions\Site;

use App\Enums\SiteWebDataStatus;
use App\Models\Site;
use App\Services\SiteAudit\WebsiteAnalyzer;
use App\Services\SiteAudit\WebsiteAuditReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CollectSiteWebData
{
    private const USER_AGENT = 'Set33SiteBot/1.0 (+https://set33.com)';

    private const MAX_FAVICON_BYTES = 512_000;

    public function __construct(
        private WebsiteAnalyzer $analyzer,
    ) {}

    public function handle(Site $site): Site
    {
        $site->forceFill([
            'web_data_status' => SiteWebDataStatus::Pending,
            'web_data_error' => null,
        ])->save();

        try {
            $report = $this->analyzer->analyze($site->url);
            $audit = $report->toArray();

            if ($report->status !== 'ready') {
                $site->forceFill([
                    'web_data_status' => SiteWebDataStatus::Failed,
                    'site_audit' => $audit,
                    'web_data_error' => Str::limit($report->error ?? 'Website audit failed', 2000),
                    'web_data_fetched_at' => now(),
                ])->save();

                return $site->refresh();
            }

            $favicon = $this->storeFavicon(
                $site,
                $this->faviconCandidates($report),
                $report->finalUrl ?? $site->url,
            );

            if (filled($site->favicon_path) && $site->favicon_path !== ($favicon['path'] ?? null)) {
                Storage::disk('public')->delete($site->favicon_path);
            }

            $site->forceFill([
                'web_data_status' => SiteWebDataStatus::Ready,
                'page_title' => $report->title,
                'meta_description' => $report->metaDescription,
                'meta_keywords' => $report->metaKeywords,
                'og_title' => $report->ogTitle ?? $report->title,
                'og_description' => $report->ogDescription,
                'og_image_url' => $report->ogImageUrl,
                'canonical_url' => $report->canonicalUrl,
                'html_lang' => $report->htmlLang,
                'favicon_path' => $favicon['path'] ?? null,
                'favicon_source_url' => $favicon['source_url'] ?? null,
                'robots_txt' => $report->robotsTxt['content'] ?? null,
                'site_audit' => $audit,
                'web_data_fetched_at' => now(),
                'web_data_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $site->forceFill([
                'web_data_status' => SiteWebDataStatus::Failed,
                'web_data_error' => Str::limit($exception->getMessage(), 2000),
                'web_data_fetched_at' => now(),
            ])->save();
        }

        return $site->refresh();
    }

    /**
     * @return list<string>
     */
    private function faviconCandidates(WebsiteAuditReport $report): array
    {
        $candidates = [];

        foreach ($report->icons['urls'] ?? [] as $url) {
            if (is_string($url) && $url !== '') {
                $candidates[] = $url;
            }
        }

        $origin = $this->originUrl($report->finalUrl ?? $report->requestedUrl);

        if ($origin !== null) {
            $candidates[] = $origin.'/favicon.ico';
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  list<string>  $candidates
     * @return array{path: string, source_url: string}|null
     */
    private function storeFavicon(Site $site, array $candidates, string $baseUrl): ?array
    {
        foreach ($candidates as $candidate) {
            $absolute = $this->absolutizeUrl($candidate, $baseUrl);

            if ($absolute === null) {
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'Accept' => 'image/*,*/*;q=0.8',
                    'User-Agent' => self::USER_AGENT,
                ])
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->get($absolute);

                if (! $response->successful()) {
                    continue;
                }

                $body = $response->body();

                if ($body === '' || strlen($body) > self::MAX_FAVICON_BYTES) {
                    continue;
                }

                $extension = $this->faviconExtension(
                    $response->header('Content-Type'),
                    $absolute,
                    $body,
                );

                if ($extension === null) {
                    continue;
                }

                $directory = 'sites/'.$site->id;
                $path = $directory.'/favicon.'.$extension;

                Storage::disk('public')->makeDirectory($directory);
                Storage::disk('public')->put($path, $body, 'public');

                return [
                    'path' => $path,
                    'source_url' => $absolute,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function faviconExtension(?string $contentType, string $url, string $body): ?string
    {
        $type = Str::lower((string) $contentType);

        if (str_contains($type, 'svg')) {
            return 'svg';
        }

        if (str_contains($type, 'png')) {
            return 'png';
        }

        if (str_contains($type, 'jpeg') || str_contains($type, 'jpg')) {
            return 'jpg';
        }

        if (str_contains($type, 'gif')) {
            return 'gif';
        }

        if (str_contains($type, 'webp')) {
            return 'webp';
        }

        if (str_contains($type, 'ico') || str_contains($type, 'x-icon')) {
            return 'ico';
        }

        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        foreach (['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'] as $extension) {
            if (Str::endsWith($path, '.'.$extension)) {
                return $extension === 'jpeg' ? 'jpg' : $extension;
            }
        }

        if (str_starts_with($body, "\x89PNG")) {
            return 'png';
        }

        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'jpg';
        }

        if (str_starts_with($body, 'GIF8')) {
            return 'gif';
        }

        if (str_contains(Str::lower(substr($body, 0, 200)), '<svg')) {
            return 'svg';
        }

        return 'ico';
    }

    private function absolutizeUrl(?string $url, string $baseUrl): ?string
    {
        $url = $this->normalizeText($url, 2048);

        if ($url === null) {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$url;
        }

        $parts = parse_url($baseUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (Str::startsWith($url, '/')) {
            return $origin.$url;
        }

        $basePath = $parts['path'] ?? '/';
        $directory = Str::endsWith($basePath, '/')
            ? $basePath
            : Str::beforeLast($basePath, '/').'/';

        return $origin.$directory.$url;
    }

    private function originUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function normalizeText(?string $value, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $max, '');
    }
}
