<?php

namespace App\Models;

use App\Enums\GoogleConnectionStatus;
use Database\Factories\GoogleConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleConnection extends Model
{
    /** @use HasFactory<GoogleConnectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'google_account_email',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
            'status' => GoogleConnectionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SiteGoogleIntegration, $this>
     */
    public function siteIntegrations(): HasMany
    {
        return $this->hasMany(SiteGoogleIntegration::class);
    }

    /**
     * @return HasMany<SitePageSpeedIntegration, $this>
     */
    public function pagespeedIntegrations(): HasMany
    {
        return $this->hasMany(SitePageSpeedIntegration::class);
    }

    public function accessTokenExpired(): bool
    {
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->lessThanOrEqualTo(now()->addMinute());
    }

    public function needsReauth(): bool
    {
        return $this->status === GoogleConnectionStatus::NeedsReauth
            || ! filled($this->refresh_token);
    }
}
