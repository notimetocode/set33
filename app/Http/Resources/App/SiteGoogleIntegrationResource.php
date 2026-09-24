<?php

namespace App\Http\Resources\App;

use App\Models\SiteGoogleIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteGoogleIntegration
 */
class SiteGoogleIntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ga4_property_id' => $this->ga4_property_id,
            'gsc_site_url' => $this->gsc_site_url,
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
