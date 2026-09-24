<?php

namespace App\Models;

use App\Enums\SiteGithubIntegrationStatus;
use Database\Factories\SiteGithubIntegrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteGithubIntegration extends Model
{
    /** @use HasFactory<SiteGithubIntegrationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'github_connection_id',
        'repository_id',
        'repository_owner',
        'repository_name',
        'repository_full_name',
        'default_branch',
        'status',
        'last_synced_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'repository_id' => 'integer',
            'status' => SiteGithubIntegrationStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<GithubConnection, $this>
     */
    public function githubConnection(): BelongsTo
    {
        return $this->belongsTo(GithubConnection::class);
    }

    public function isConfigured(): bool
    {
        return filled($this->repository_full_name) && filled($this->default_branch);
    }
}
