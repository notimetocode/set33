<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Actions\Auth\IssueApiToken;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(
        LoginRequest $request,
        AttemptLogin $attemptLogin,
        IssueApiToken $issueApiToken,
    ): JsonResponse {
        $user = $attemptLogin->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if ($user->role !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $issueApiToken->handle($user, 'admin', ['admin']);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new AuthenticatedUserResource($user))->resolve(),
        ]);
    }
}
