<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_name',
        'first_name',
        'middle_name',
        'gender',
        'birth_date',
        'phone',
        'telegram',
        'viber',
        'avatar_path',
        'city_id',
        'profile_visibility',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'profile_visibility' => '{"last_name":true,"birth_date":true,"phone":false,"telegram":false,"viber":false}',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'gender' => Gender::class,
            'birth_date' => 'date',
            'profile_visibility' => 'array',
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasMany<AiService, $this>
     */
    public function aiServices(): HasMany
    {
        return $this->hasMany(AiService::class);
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasOne<GoogleConnection, $this>
     */
    public function googleConnection(): HasOne
    {
        return $this->hasOne(GoogleConnection::class);
    }

    /**
     * @return HasOne<GithubConnection, $this>
     */
    public function githubConnection(): HasOne
    {
        return $this->hasOne(GithubConnection::class);
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    /**
     * @return array{last_name: bool, birth_date: bool, phone: bool, telegram: bool, viber: bool}
     */
    public function visibilityFlags(): array
    {
        $defaults = [
            'last_name' => true,
            'birth_date' => true,
            'phone' => false,
            'telegram' => false,
            'viber' => false,
        ];

        $stored = is_array($this->profile_visibility) ? $this->profile_visibility : [];

        return array_merge($defaults, array_intersect_key($stored, $defaults));
    }
}
