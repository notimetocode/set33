<?php

namespace App\Http\Controllers\App;

use App\Actions\Profile\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Profile\UpdateProfileRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        Gate::authorize('app.profile.view', $user);

        return response()->json([
            'data' => (new AuthenticatedUserResource($user))->resolve(),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $update): JsonResponse
    {
        $user = $request->user();

        Gate::authorize('app.profile.update', $user);

        $user = $update->handle($user, $request->validated());

        return response()->json([
            'data' => (new AuthenticatedUserResource($user))->resolve(),
        ]);
    }
}
