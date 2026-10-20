<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\StoreDiscoverCheckRequest;
use App\Services\Discover\DiscoverArticleChecker;
use Illuminate\Http\JsonResponse;

class DiscoverCheckController extends Controller
{
    public function store(StoreDiscoverCheckRequest $request, DiscoverArticleChecker $checker): JsonResponse
    {
        $skip = $request->validated('skip') ?? [];

        return response()->json([
            'data' => $checker->check($request->validated('url'), $skip),
        ]);
    }
}
