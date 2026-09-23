<?php

namespace App\Http\Controllers\App\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Actions\Auth\IssueApiToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;

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

        $token = $issueApiToken->handle($user, 'app', ['app']);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new AuthenticatedUserResource($user))->resolve(),
        ]);
    }
}
