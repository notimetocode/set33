<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'name',
    'url',
])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<SiteGoogleIntegration, $this>
     */
    public function googleIntegration(): HasOne
    {
        return $this->hasOne(SiteGoogleIntegration::class);
    }

    /**
     * @return HasOne<SiteGithubIntegration, $this>
     */
    public function githubIntegration(): HasOne
    {
        return $this->hasOne(SiteGithubIntegration::class);
    }

    /**
     * @return HasMany<SiteAnalyticsDaily, $this>
     */
    public function analyticsDaily(): HasMany
    {
        return $this->hasMany(SiteAnalyticsDaily::class);
    }

    /**
     * @return HasMany<SiteSearchConsoleDaily, $this>
     */
    public function searchConsoleDaily(): HasMany
    {
        return $this->hasMany(SiteSearchConsoleDaily::class);
    }

    /**
     * @return HasMany<SiteGithubCommit, $this>
     */
    public function githubCommits(): HasMany
    {
        return $this->hasMany(SiteGithubCommit::class);
    }
}
