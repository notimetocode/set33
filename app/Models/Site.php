<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'url',
    ];

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
     * @return HasOne<SitePageSpeedIntegration, $this>
     */
    public function pagespeedIntegration(): HasOne
    {
        return $this->hasOne(SitePageSpeedIntegration::class);
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
     * @return HasMany<SiteSearchConsoleDimension, $this>
     */
    public function searchConsoleDimensions(): HasMany
    {
        return $this->hasMany(SiteSearchConsoleDimension::class);
    }

    /**
     * @return HasMany<SiteSearchConsoleSitemap, $this>
     */
    public function searchConsoleSitemaps(): HasMany
    {
        return $this->hasMany(SiteSearchConsoleSitemap::class);
    }

    /**
     * @return HasMany<SiteUrlInspection, $this>
     */
    public function urlInspections(): HasMany
    {
        return $this->hasMany(SiteUrlInspection::class);
    }

    /**
     * @return HasMany<SitePageSpeedLabSnapshot, $this>
     */
    public function pagespeedLabSnapshots(): HasMany
    {
        return $this->hasMany(SitePageSpeedLabSnapshot::class);
    }

    /**
     * @return HasMany<SiteCruxSnapshot, $this>
     */
    public function cruxSnapshots(): HasMany
    {
        return $this->hasMany(SiteCruxSnapshot::class);
    }

    /**
     * @return HasMany<SiteGithubCommit, $this>
     */
    public function githubCommits(): HasMany
    {
        return $this->hasMany(SiteGithubCommit::class);
    }

    /**
     * @return HasMany<SiteAiReport, $this>
     */
    public function aiReports(): HasMany
    {
        return $this->hasMany(SiteAiReport::class);
    }

    /**
     * @return HasMany<SiteEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SiteEvent::class);
    }
}
