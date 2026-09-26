<?php

namespace App\Models;

use Database\Factories\SiteCruxSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteCruxSnapshot extends Model
{
    /** @use HasFactory<SiteCruxSnapshotFactory> */
    use HasFactory;

    protected $table = 'site_crux_snapshots';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'scope',
        'url',
        'form_factor',
        'collection_period_start',
        'collection_period_end',
        'lcp_p75_ms',
        'inp_p75_ms',
        'cls_p75',
        'fcp_p75_ms',
        'ttfb_p75_ms',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection_period_start' => 'date',
            'collection_period_end' => 'date',
            'lcp_p75_ms' => 'integer',
            'inp_p75_ms' => 'integer',
            'cls_p75' => 'float',
            'fcp_p75_ms' => 'integer',
            'ttfb_p75_ms' => 'integer',
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
