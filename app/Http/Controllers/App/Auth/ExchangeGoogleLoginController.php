<?php

namespace App\Http\Controllers\App\Auth;

use App\Actions\Auth\ExchangeGoogleLoginCode;
use App\Actions\Auth\IssueApiToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\ExchangeGoogleLoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;

class ExchangeGoogleLoginController extends Controller
{
    public function __invoke(
        ExchangeGoogleLoginRequest $request,
        ExchangeGoogleLoginCode $exchangeGoogleLoginCode,
        IssueApiToken $issueApiToken,
    ): JsonResponse {
        $user = $exchangeGoogleLoginCode->handle(
            $request->string('code')->toString(),
        );

        $token = $issueApiToken->handle($user, 'app', ['app']);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new AuthenticatedUserResource($user))->resolve(),
        ]);
    }
}
