<?php

namespace App\Models;

use Database\Factories\SitePageSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePageSnapshot extends Model
{
    /** @use HasFactory<SitePageSnapshotFactory> */
    use HasFactory;

    protected $table = 'site_page_snapshots';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'url',
        'final_url',
        'page_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image_url',
        'canonical_url',
        'html_lang',
        'fetched_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
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
