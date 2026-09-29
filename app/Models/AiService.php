<?php

namespace App\Models;

use App\Enums\AiServiceStatus;
use App\Enums\AiServiceType;
use Database\Factories\AiServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiService extends Model
{
    /** @use HasFactory<AiServiceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'is_global',
        'name',
        'type',
        'api_key',
        'settings',
        'status',
        'status_message',
        'status_checked_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_global' => false,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'type' => AiServiceType::class,
            'status' => AiServiceStatus::class,
            'api_key' => 'encrypted',
            'settings' => 'array',
            'status_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<AiService>  $query
     * @return Builder<AiService>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }

    /**
     * @param  Builder<AiService>  $query
     * @return Builder<AiService>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped->where('user_id', $user->id)
                ->orWhere('is_global', true);
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    public function markStatusUnchecked(): void
    {
        $this->forceFill([
            'status' => AiServiceStatus::Unchecked,
            'status_message' => null,
            'status_checked_at' => null,
        ])->save();
    }
}
