<?php

namespace App\Models;

use App\Enums\AiServiceStatus;
use App\Enums\AiServiceType;
use Database\Factories\AiServiceFactory;
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
        'name',
        'type',
        'api_key',
        'settings',
        'status',
        'status_message',
        'status_checked_at',
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
