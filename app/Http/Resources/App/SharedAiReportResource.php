<?php

namespace App\Http\Resources\App;

use App\Models\SiteAiReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteAiReport
 */
class SharedAiReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period' => [
                'from' => $this->period_from?->toDateString(),
                'to' => $this->period_to?->toDateString(),
            ],
            'site' => [
                'name' => $this->site?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'reply' => $this->reply,
            'charts' => $this->charts ?? [],
        ];
    }
}
