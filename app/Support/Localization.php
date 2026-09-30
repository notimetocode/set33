<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class Localization
{
    public static function defaultLocale(): string
    {
        return (string) config('localization.default', 'en');
    }

    /**
     * @return list<string>
     */
    public static function availableLocales(): array
    {
        /** @var list<string> $locales */
        $locales = config('localization.available', ['en']);

        return $locales;
    }

    /**
     * @return list<string>
     */
    public static function nonDefaultLocales(): array
    {
        $default = self::defaultLocale();

        return array_values(array_filter(
            self::availableLocales(),
            static fn (string $locale): bool => $locale !== $default,
        ));
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::availableLocales(), true);
    }

    public static function label(string $locale): string
    {
        /** @var array<string, string> $labels */
        $labels = config('localization.labels', []);

        return $labels[$locale] ?? strtoupper($locale);
    }

    public static function intl(string $locale): string
    {
        /** @var array<string, string> $map */
        $map = config('localization.intl', []);

        return $map[$locale] ?? $locale;
    }

    /**
     * Regex fragment for non-default locale route prefixes (e.g. "ru|pl").
     */
    public static function nonDefaultLocalesPattern(): string
    {
        $locales = self::nonDefaultLocales();

        if ($locales === []) {
            return '___none___';
        }

        return implode('|', array_map('preg_quote', $locales));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $default = self::defaultLocale();

        if (! self::isSupported($locale)) {
            $locale = $default;
        }

        unset($parameters['locale']);

        $path = route($name, $parameters, absolute: false);

        if ($locale === $default) {
            return url($path);
        }

        if ($path === '/') {
            return url('/'.$locale);
        }

        return url('/'.$locale.$path);
    }

    /**
     * Alternate URLs for the current named route (hreflang).
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, string> locale => absolute URL
     */
    public static function alternateUrls(?string $routeName = null, array $parameters = []): array
    {
        $routeName ??= Route::currentRouteName();

        if ($routeName === null || ! str_starts_with($routeName, 'public.')) {
            // Prefixed locale routes are unnamed — recover from path.
            $routeName = self::guessPublicRouteName();
        }

        if ($routeName === null) {
            return [];
        }

        $current = Route::current();
        $parameters = $parameters !== []
            ? $parameters
            : ($current?->parameters() ?? []);

        unset($parameters['locale']);

        $urls = [];

        foreach (self::availableLocales() as $locale) {
            $urls[$locale] = self::route($routeName, $parameters, $locale);
        }

        return $urls;
    }

    private static function guessPublicRouteName(): ?string
    {
        $path = '/'.ltrim(request()->path(), '/');

        foreach (self::nonDefaultLocales() as $locale) {
            $prefix = '/'.$locale;

            if ($path === $prefix) {
                return 'public.home';
            }

            if (str_starts_with($path, $prefix.'/')) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return match (true) {
            $path === '/' || $path === '' => 'public.home',
            $path === '/privacy' => 'public.privacy',
            $path === '/terms' => 'public.terms',
            (bool) preg_match('#^/r/[A-Za-z0-9]{20,64}$#', $path) => 'public.ai-reports.show',
            default => null,
        };
    }
}
