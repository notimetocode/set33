<?php

namespace App\Http\Resources\App;

use App\Models\SitePageSpeedIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SitePageSpeedIntegration
 */
class SitePageSpeedIntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'strategy' => $this->strategy->value,
            'strategy_label' => $this->strategy->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'is_configured' => $this->resource->isConfigured(),
            'google_connection' => $this->whenLoaded(
                'googleConnection',
                fn () => $this->googleConnection
                    ? new GoogleConnectionResource($this->googleConnection)
                    : null,
            ),
        ];
    }
}
