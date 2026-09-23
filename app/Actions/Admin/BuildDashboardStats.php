<?php

namespace App\Actions\Admin;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;

class BuildDashboardStats
{
    /**
     * @return array{
     *     users: array{
     *         total: int,
     *         admins: int,
     *         users: int,
     *         registered_this_week: int,
     *         registered_this_month: int,
     *         by_gender: list<array{value: string|null, label: string, count: int}>
     *     }
     * }
     */
    public function handle(?Carbon $now = null): array
    {
        $now ??= now();
        $weekStart = $now->copy()->subDays(7);
        $monthStart = $now->copy()->startOfMonth();

        $userRoleCounts = User::query()
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        $admins = (int) ($userRoleCounts[UserRole::Admin->value] ?? 0);
        $users = (int) ($userRoleCounts[UserRole::User->value] ?? 0);

        $genderCounts = User::query()
            ->selectRaw('gender, count(*) as aggregate')
            ->groupBy('gender')
            ->pluck('aggregate', 'gender');

        $byGender = [];

        foreach (Gender::cases() as $gender) {
            $byGender[] = [
                'value' => $gender->value,
                'label' => $gender->label(),
                'count' => (int) ($genderCounts[$gender->value] ?? 0),
            ];
        }

        $nullGenderCount = (int) User::query()->whereNull('gender')->count();

        if ($nullGenderCount > 0) {
            $byGender[] = [
                'value' => null,
                'label' => 'Не указан',
                'count' => $nullGenderCount,
            ];
        }

        return [
            'users' => [
                'total' => $admins + $users,
                'admins' => $admins,
                'users' => $users,
                'registered_this_week' => User::query()->where('created_at', '>=', $weekStart)->count(),
                'registered_this_month' => User::query()->where('created_at', '>=', $monthStart)->count(),
                'by_gender' => $byGender,
            ],
        ];
    }
}
