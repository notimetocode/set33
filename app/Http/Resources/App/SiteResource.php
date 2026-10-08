<?php

namespace App\Http\Resources\App;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'web_data_status' => $this->web_data_status?->value,
            'page_title' => $this->page_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image_url' => $this->og_image_url,
            'canonical_url' => $this->canonical_url,
            'html_lang' => $this->html_lang,
            'favicon_url' => $this->faviconUrl(),
            'favicon_source_url' => $this->favicon_source_url,
            'robots_txt' => $this->robots_txt,
            'site_audit' => $this->site_audit,
            'web_data_fetched_at' => $this->web_data_fetched_at?->toIso8601String(),
            'web_data_error' => $this->web_data_error,
            'google_integration' => $this->whenLoaded(
                'googleIntegration',
                fn () => $this->googleIntegration
                    ? new SiteGoogleIntegrationResource($this->googleIntegration)
                    : null,
            ),
            'github_integration' => $this->whenLoaded(
                'githubIntegration',
                fn () => $this->githubIntegration
                    ? new SiteGithubIntegrationResource($this->githubIntegration)
                    : null,
            ),
            'pagespeed_integration' => $this->whenLoaded(
                'pagespeedIntegration',
                fn () => $this->pagespeedIntegration
                    ? new SitePageSpeedIntegrationResource($this->pagespeedIntegration)
                    : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
