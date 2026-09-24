<?php

namespace App\Models;

use Database\Factories\SiteAnalyticsDailyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'date',
    'sessions',
    'total_users',
    'new_users',
    'screen_page_views',
    'organic_sessions',
    'organic_total_users',
    'organic_new_users',
])]
class SiteAnalyticsDaily extends Model
{
    /** @use HasFactory<SiteAnalyticsDailyFactory> */
    use HasFactory;

    protected $table = 'site_analytics_daily';

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
