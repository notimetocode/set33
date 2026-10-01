<?php

namespace App\Models;

use Database\Factories\SiteAnalyticsPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAnalyticsPage extends Model
{
    /** @use HasFactory<SiteAnalyticsPageFactory> */
    use HasFactory;

    protected $table = 'site_analytics_pages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'period_from',
        'period_to',
        'page_path',
        'rank',
        'sessions',
        'screen_page_views',
        'total_users',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'rank' => 'integer',
            'sessions' => 'integer',
            'screen_page_views' => 'integer',
            'total_users' => 'integer',
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
