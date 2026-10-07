<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        $default = Localization::defaultLocale();

        if (! is_string($locale) || $locale === '') {
            $locale = $default;
        }

        if (! Localization::isSupported($locale)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        app()->setLocale($locale);

        // Locale is only a URL prefix — drop it so controllers receive
        // subsequent route params (e.g. {token}) by position correctly.
        $request->route()?->forgetParameter('locale');

        return $next($request);
    }
}
