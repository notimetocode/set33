<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\IndexUserRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function meta(): JsonResponse
    {
        Gate::authorize('admin.users.viewAny');

        return response()->json([
            'data' => [
                'roles' => UserRole::options(),
                'genders' => Gender::options(),
            ],
        ]);
    }

    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('admin.users.viewAny');

        $users = User::query()
            ->with('city')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return UserResource::collection($users);
    }

    private function applyFilters(Builder $query, IndexUserRequest $request): void
    {
        $term = trim((string) $request->validated('q', ''));

        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->where(function (Builder $search) use ($like): void {
                $search->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('telegram', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhereRaw(
                        "trim(concat_ws(' ', coalesce(last_name, ''), coalesce(first_name, ''), coalesce(middle_name, ''))) like ?",
                        [$like]
                    );
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->validated('role'));
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->validated('gender'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->validated('city_id'));
        }
    }
}
