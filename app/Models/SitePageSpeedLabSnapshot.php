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
        'accessibility_score',
        'best_practices_score',
        'seo_score',
        'lcp_ms',
        'inp_ms',
        'cls',
        'fcp_ms',
        'ttfb_ms',
        'tbt_ms',
        'speed_index_ms',
        'payload',
        'include_details_in_report',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'performance_score' => 'integer',
            'accessibility_score' => 'integer',
            'best_practices_score' => 'integer',
            'seo_score' => 'integer',
            'lcp_ms' => 'integer',
            'inp_ms' => 'integer',
            'cls' => 'float',
            'fcp_ms' => 'integer',
            'ttfb_ms' => 'integer',
            'tbt_ms' => 'integer',
            'speed_index_ms' => 'integer',
            'payload' => 'array',
            'include_details_in_report' => 'boolean',
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
