<?php

namespace App\Http\Resources\App;

use App\Models\SiteGithubIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteGithubIntegration
 */
class SiteGithubIntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repository_id' => $this->repository_id,
            'repository_owner' => $this->repository_owner,
            'repository_name' => $this->repository_name,
            'repository_full_name' => $this->repository_full_name,
            'default_branch' => $this->default_branch,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'is_configured' => $this->resource->isConfigured(),
            'github_connection' => $this->whenLoaded(
                'githubConnection',
                fn () => $this->githubConnection
                    ? new GithubConnectionResource($this->githubConnection)
                    : null,
            ),
        ];
    }
}
