<?php

namespace App\Actions\Profile;

use App\Models\User;
use Illuminate\Support\Str;

class UpdateProfile
{
    /**
     * @param  array{
     *     last_name?: string|null,
     *     first_name?: string|null,
     *     middle_name?: string|null,
     *     email: string,
     *     phone?: string|null,
     *     telegram?: string|null,
     *     viber?: string|null
     * }  $data
     */
    public function handle(User $user, array $data): User
    {
        $firstName = $this->nullableString($data['first_name'] ?? null);
        $lastName = $this->nullableString($data['last_name'] ?? null);
        $middleName = $this->nullableString($data['middle_name'] ?? null);

        $user->fill([
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'name' => $this->displayName($firstName, $lastName, $middleName, $data['email']),
            'email' => $data['email'],
            'phone' => $this->nullableString($data['phone'] ?? null),
            'telegram' => $this->nullableString($data['telegram'] ?? null),
            'viber' => $this->nullableString($data['viber'] ?? null),
        ])->save();

        return $user->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function displayName(?string $firstName, ?string $lastName, ?string $middleName, string $email): string
    {
        $fullName = trim(implode(' ', array_filter([$lastName, $firstName, $middleName])));

        if ($fullName !== '') {
            return Str::limit($fullName, 255, '');
        }

        return Str::before($email, '@') ?: $email;
    }
}
