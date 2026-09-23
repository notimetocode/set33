<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullName = trim(implode(' ', array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ], fn (?string $part): bool => filled($part))));

        $avatarUrl = null;

        if (filled($this->avatar_path)) {
            $avatarUrl = Storage::disk('public')->url($this->avatar_path);
        }

        return [
            'id' => $this->id,
            'name' => $fullName !== '' ? $fullName : ($this->name ?: 'Без имени'),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'telegram' => $this->telegram,
            'role' => $this->role?->value,
            'role_label' => $this->role?->label(),
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'city' => CityResource::make($this->whenLoaded('city')),
            'avatar' => [
                'sm' => $avatarUrl,
                'md' => $avatarUrl,
            ],
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
