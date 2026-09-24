<?php

namespace App\Models;

use Database\Factories\SiteGithubCommitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteGithubCommit extends Model
{
    /** @use HasFactory<SiteGithubCommitFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'sha',
        'message',
        'html_url',
        'author_name',
        'author_email',
        'author_date',
        'committer_name',
        'committer_email',
        'committer_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'author_date' => 'datetime',
            'committer_date' => 'datetime',
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
