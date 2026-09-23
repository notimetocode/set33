<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        $required = UserRole::tryFrom($role);

        if (! $user || $required === null || $user->role !== $required) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
