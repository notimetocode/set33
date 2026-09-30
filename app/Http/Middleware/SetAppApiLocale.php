<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAppApiLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $user = $request->user();

        if ($user !== null && is_string($user->locale) && Localization::isSupported($user->locale)) {
            return $user->locale;
        }

        $preferred = $request->getPreferredLanguage(Localization::availableLocales());

        if (is_string($preferred) && Localization::isSupported($preferred)) {
            return $preferred;
        }

        return Localization::defaultLocale();
    }
}
