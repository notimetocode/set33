<?php

namespace App\Http\Resources\App;

use App\Models\SiteEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteEvent
 */
class SiteEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_on' => $this->occurred_on?->toDateString(),
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
