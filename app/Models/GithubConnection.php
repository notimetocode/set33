<?php

namespace App\Models;

use App\Enums\GithubConnectionStatus;
use Database\Factories\GithubConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'github_user_id',
    'github_login',
    'github_account_email',
    'access_token',
    'refresh_token',
    'expires_at',
    'scopes',
    'status',
])]
#[Hidden(['access_token', 'refresh_token'])]
class GithubConnection extends Model
{
    /** @use HasFactory<GithubConnectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'github_user_id' => 'integer',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
            'status' => GithubConnectionStatus::class,
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
     * @return HasMany<SiteGithubIntegration, $this>
     */
    public function siteIntegrations(): HasMany
    {
        return $this->hasMany(SiteGithubIntegration::class);
    }

    public function accessTokenExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->lessThanOrEqualTo(now()->addMinute());
    }

    public function needsReauth(): bool
    {
        return $this->status === GithubConnectionStatus::NeedsReauth;
    }
}
