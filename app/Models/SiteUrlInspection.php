<?php

namespace App\Models;

use Database\Factories\SiteUrlInspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteUrlInspection extends Model
{
    /** @use HasFactory<SiteUrlInspectionFactory> */
    use HasFactory;

    protected $table = 'site_url_inspections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'inspected_url',
        'period_from',
        'period_to',
        'verdict',
        'coverage_state',
        'indexing_state',
        'page_fetch_state',
        'robots_txt_state',
        'crawled_as',
        'last_crawl_time',
        'google_canonical',
        'user_canonical',
        'inspection_result_link',
        'referring_urls',
        'sitemaps',
        'inspected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'last_crawl_time' => 'datetime',
            'inspected_at' => 'datetime',
            'referring_urls' => 'array',
            'sitemaps' => 'array',
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
