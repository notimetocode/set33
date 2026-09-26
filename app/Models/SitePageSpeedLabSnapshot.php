<?php

namespace App\Models;

use Database\Factories\SitePageSpeedLabSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePageSpeedLabSnapshot extends Model
{
    /** @use HasFactory<SitePageSpeedLabSnapshotFactory> */
    use HasFactory;

    protected $table = 'site_pagespeed_lab_snapshots';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'url',
        'strategy',
        'fetched_at',
        'performance_score',
        'lcp_ms',
        'inp_ms',
        'cls',
        'fcp_ms',
        'ttfb_ms',
        'tbt_ms',
        'speed_index_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'performance_score' => 'integer',
            'lcp_ms' => 'integer',
            'inp_ms' => 'integer',
            'cls' => 'float',
            'fcp_ms' => 'integer',
            'ttfb_ms' => 'integer',
            'tbt_ms' => 'integer',
            'speed_index_ms' => 'integer',
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
