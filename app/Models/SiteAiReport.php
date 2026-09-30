<?php

namespace App\Models;

use App\Enums\AiReportVisibility;
use App\Support\Localization;
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
        'use_system_prompt',
        'prompt',
        'reply',
        'charts',
        'usage',
        'data_counts',
        'locale',
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
        'use_system_prompt' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'use_system_prompt' => 'boolean',
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

        return localized_route(
            'public.ai-reports.show',
            ['token' => $this->share_token],
            $this->shareLocale(),
        );
    }

    public function shareLocale(): string
    {
        $locale = is_string($this->locale) ? $this->locale : null;

        if ($locale !== null && Localization::isSupported($locale)) {
            return $locale;
        }

        $appLocale = app()->getLocale();

        if (Localization::isSupported($appLocale)) {
            return $appLocale;
        }

        return Localization::defaultLocale();
    }

    public function hasSharePassword(): bool
    {
        return $this->visibility === AiReportVisibility::Password
            && filled($this->share_password);
    }
}
