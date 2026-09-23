<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BuildDashboardStats;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(BuildDashboardStats $buildDashboardStats): JsonResponse
    {
        Gate::authorize('admin.dashboard.view');

        return response()->json([
            'data' => $buildDashboardStats->handle(),
        ]);
    }
}
