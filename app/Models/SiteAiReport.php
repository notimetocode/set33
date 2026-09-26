<?php

namespace App\Models;

use App\Enums\AiReportVisibility;
use Database\Factories\SiteAiReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAiReport extends Model
{
    /** @use HasFactory<SiteAiReportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'ai_service_id',
        'ai_service_name',
        'ai_service_type',
        'model',
        'period_from',
        'period_to',
        'reply',
        'charts',
        'usage',
        'data_counts',
        'visibility',
        'share_token',
        'share_password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'share_password',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'private',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'charts' => 'array',
            'usage' => 'array',
            'data_counts' => 'array',
            'visibility' => AiReportVisibility::class,
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

    public function shareUrl(): ?string
    {
        if (! $this->visibility->isShared() || ! filled($this->share_token)) {
            return null;
        }

        return route('public.ai-reports.show', ['token' => $this->share_token]);
    }

    public function hasSharePassword(): bool
    {
        return $this->visibility === AiReportVisibility::Password
            && filled($this->share_password);
    }
}
