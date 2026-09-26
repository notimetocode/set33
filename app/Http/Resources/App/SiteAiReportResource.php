<?php

namespace App\Http\Resources\App;

use App\Models\SiteAiReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteAiReport
 */
class SiteAiReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'period' => [
                'from' => $this->period_from?->toDateString(),
                'to' => $this->period_to?->toDateString(),
            ],
            'tool' => [
                'ai_service_id' => $this->ai_service_id,
                'name' => $this->ai_service_name,
                'type' => $this->ai_service_type,
                'model' => $this->model,
                'label' => $this->toolLabel(),
            ],
            'data_counts' => $this->data_counts,
            'usage' => $this->usage,
            'sharing' => [
                'visibility' => $this->visibility->value,
                'share_url' => $this->shareUrl(),
                'has_password' => $this->hasSharePassword(),
            ],
            'reply' => $this->when(
                ! $request->routeIs('app.sites.ai-reports.index'),
                $this->reply,
            ),
            'charts' => $this->when(
                ! $request->routeIs('app.sites.ai-reports.index'),
                $this->charts ?? [],
            ),
        ];
    }
}
