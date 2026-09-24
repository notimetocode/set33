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
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
