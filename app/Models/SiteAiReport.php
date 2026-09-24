<?php

namespace App\Models;

use Database\Factories\SiteAiReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'ai_service_id',
    'ai_service_name',
    'ai_service_type',
    'model',
    'period_from',
    'period_to',
    'reply',
    'usage',
    'data_counts',
])]
class SiteAiReport extends Model
{
    /** @use HasFactory<SiteAiReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'usage' => 'array',
            'data_counts' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<AiService, $this>
     */
    public function aiService(): BelongsTo
    {
        return $this->belongsTo(AiService::class);
    }

    public function toolLabel(): string
    {
        if (filled($this->model) && ! str_contains($this->ai_service_name, $this->model)) {
            return $this->ai_service_name.' · '.$this->model;
        }

        return $this->ai_service_name;
    }
}
