<?php

namespace App\Http\Controllers\App\Auth;

use App\Actions\Auth\IssueApiToken;
use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\RegisterRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        RegisterUser $registerUser,
        IssueApiToken $issueApiToken,
    ): JsonResponse {
        $user = $registerUser->handle([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        $token = $issueApiToken->handle($user, 'app', ['app']);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new AuthenticatedUserResource($user))->resolve(),
        ], 201);
    }
}
