<?php

namespace App\Models;

use Database\Factories\SiteSearchConsoleDailyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSearchConsoleDaily extends Model
{
    /** @use HasFactory<SiteSearchConsoleDailyFactory> */
    use HasFactory;

    protected $table = 'site_search_console_daily';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'date',
        'clicks',
        'impressions',
        'ctr',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'ctr' => 'float',
            'position' => 'float',
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
