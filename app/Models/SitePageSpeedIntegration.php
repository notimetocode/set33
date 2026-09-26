<?php

namespace App\Models;

use App\Enums\GoogleConnectionStatus;
use App\Enums\PageSpeedStrategy;
use App\Enums\SitePageSpeedIntegrationStatus;
use Database\Factories\SitePageSpeedIntegrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePageSpeedIntegration extends Model
{
    /** @use HasFactory<SitePageSpeedIntegrationFactory> */
    use HasFactory;

    protected $table = 'site_pagespeed_integrations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'google_connection_id',
        'strategy',
        'status',
        'last_synced_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strategy' => PageSpeedStrategy::class,
            'status' => SitePageSpeedIntegrationStatus::class,
            'last_synced_at' => 'datetime',
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
     * @return BelongsTo<GoogleConnection, $this>
     */
    public function googleConnection(): BelongsTo
    {
        return $this->belongsTo(GoogleConnection::class);
    }

    public function isConfigured(): bool
    {
        return $this->google_connection_id !== null
            && $this->googleConnection !== null
            && $this->googleConnection->status === GoogleConnectionStatus::Active
            && ! $this->googleConnection->needsReauth();
    }
}
