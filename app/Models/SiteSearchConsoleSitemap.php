<?php

namespace App\Models;

use Database\Factories\SiteSearchConsoleSitemapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSearchConsoleSitemap extends Model
{
    /** @use HasFactory<SiteSearchConsoleSitemapFactory> */
    use HasFactory;

    protected $table = 'site_search_console_sitemaps';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'path',
        'type',
        'is_pending',
        'is_sitemaps_index',
        'last_downloaded_at',
        'last_submitted_at',
        'errors',
        'warnings',
        'contents',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pending' => 'boolean',
            'is_sitemaps_index' => 'boolean',
            'last_downloaded_at' => 'datetime',
            'last_submitted_at' => 'datetime',
            'errors' => 'integer',
            'warnings' => 'integer',
            'contents' => 'array',
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
