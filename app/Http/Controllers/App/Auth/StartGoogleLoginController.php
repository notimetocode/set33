<?php

namespace App\Http\Controllers\App\Auth;

use App\Actions\Auth\StartGoogleLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\StartGoogleLoginRequest;
use Illuminate\Http\JsonResponse;

class StartGoogleLoginController extends Controller
{
    public function __invoke(
        StartGoogleLoginRequest $request,
        StartGoogleLogin $startGoogleLogin,
    ): JsonResponse {
        if (! filled(config('services.google.client_id')) || ! filled(config('services.google.client_secret'))) {
            return response()->json([
                'message' => __('auth.google_not_configured'),
            ], 503);
        }

        $result = $startGoogleLogin->handle(
            $request->validated('locale') ?? app()->getLocale(),
        );

        return response()->json($result);
    }
}
