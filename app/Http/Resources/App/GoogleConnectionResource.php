<?php

namespace App\Http\Resources\App;

use App\Models\GoogleConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoogleConnection
 */
class GoogleConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'google_account_email' => $this->google_account_email,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'needs_reauth' => $this->resource->needsReauth(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
