<?php

namespace App\Models;

use App\Enums\SearchConsoleDimension;
use Database\Factories\SiteSearchConsoleDimensionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'period_from',
    'period_to',
    'dimension',
    'value',
    'rank',
    'clicks',
    'impressions',
    'ctr',
    'position',
])]
class SiteSearchConsoleDimension extends Model
{
    /** @use HasFactory<SiteSearchConsoleDimensionFactory> */
    use HasFactory;

    protected $table = 'site_search_console_dimensions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'dimension' => SearchConsoleDimension::class,
            'rank' => 'integer',
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
