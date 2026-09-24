<?php

namespace App\Http\Resources\App;

use App\Models\AiService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiService
 */
class AiServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'settings' => $this->settings,
            'api_key_set' => $this->resource->hasApiKey(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_message' => $this->status_message,
            'status_checked_at' => $this->status_checked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
