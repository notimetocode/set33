<?php

namespace App\Models;

use App\Enums\SiteGoogleIntegrationStatus;
use Database\Factories\SiteGoogleIntegrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteGoogleIntegration extends Model
{
    /** @use HasFactory<SiteGoogleIntegrationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'google_connection_id',
        'ga4_property_id',
        'gsc_site_url',
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
            'status' => SiteGoogleIntegrationStatus::class,
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
        return filled($this->ga4_property_id) || filled($this->gsc_site_url);
    }
}
