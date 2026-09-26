<?php

namespace App\Models;

use Database\Factories\SiteAnalyticsDailyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAnalyticsDaily extends Model
{
    /** @use HasFactory<SiteAnalyticsDailyFactory> */
    use HasFactory;

    protected $table = 'site_analytics_daily';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'date',
        'sessions',
        'total_users',
        'new_users',
        'screen_page_views',
        'organic_sessions',
        'organic_total_users',
        'organic_new_users',
        'engaged_sessions',
        'engagement_rate',
        'bounce_rate',
        'average_session_duration',
        'event_count',
        'organic_engaged_sessions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sessions' => 'integer',
            'total_users' => 'integer',
            'new_users' => 'integer',
            'screen_page_views' => 'integer',
            'organic_sessions' => 'integer',
            'organic_total_users' => 'integer',
            'organic_new_users' => 'integer',
            'engaged_sessions' => 'integer',
            'engagement_rate' => 'float',
            'bounce_rate' => 'float',
            'average_session_duration' => 'float',
            'event_count' => 'integer',
            'organic_engaged_sessions' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
