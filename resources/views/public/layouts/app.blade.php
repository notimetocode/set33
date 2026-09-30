<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @php
        // Nested @yield inside @hasSection/@else is not compiled by Blade and leaks as literal text to crawlers.
        $pageTitle = trim($__env->yieldContent('title')) ?: config('app.name');
        $pageDescription = trim($__env->yieldContent('meta_description')) ?: config('app.name');
        $ogTitle = trim($__env->yieldContent('og_title')) ?: $pageTitle;
        $ogDescription = trim($__env->yieldContent('og_description')) ?: $pageDescription;
        $ogImage = trim($__env->yieldContent('og_image')) ?: asset('images/og-image.png');
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <x-site-icons />
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    @foreach (\App\Support\Localization::alternateUrls() as $hreflang => $href)
        @if ($hreflang !== app()->getLocale())
            <meta property="og:locale:alternate" content="{{ str_replace('_', '-', $hreflang) }}">
        @endif
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    @if ($defaultAlternate = \App\Support\Localization::alternateUrls()[\App\Support\Localization::defaultLocale()] ?? null)
        <link rel="alternate" hreflang="x-default" href="{{ $defaultAlternate }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    @stack('meta')
    @vite(['resources/scss/public.scss', 'resources/js/public/app.js'])
</head>
<body
    class="layout-public"
    data-i18n-cabinet="{{ __('public.nav.cabinet') }}"
    data-i18n-login="{{ __('public.nav.login') }}"
    data-i18n-register="{{ __('public.nav.register') }}"
    data-i18n-open-menu="{{ __('public.nav.open_menu') }}"
    data-i18n-close-menu="{{ __('public.nav.close_menu') }}"
>
    @php
        $currentLocale = app()->getLocale();
        $alternateUrls = \App\Support\Localization::alternateUrls();
    @endphp
    <header class="layout-public__header" data-public-header>
        <div class="container layout-public__header-inner">
            <nav class="layout-public__nav layout-public__nav--desktop" aria-label="{{ __('public.nav.main') }}">
                <ul class="layout-public__nav-list">
                    <li>
                        <a href="{{ localized_route('public.home') }}#how-it-works" class="layout-public__nav-link">{{ __('public.nav.how_it_works') }}</a>
                    </li>
                    <li>
                        <a href="{{ localized_route('public.home') }}#pricing" class="layout-public__nav-link">{{ __('public.nav.pricing') }}</a>
                    </li>
                </ul>
            </nav>

            <a href="{{ localized_route('public.home') }}" class="layout-public__brand">
                <span class="logo">
                    <img class="logo__mark" src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" width="120" height="49">
                </span>
            </a>

            <div class="layout-public__actions">
                <div class="lang-select" data-lang-select>
                    <button
                        type="button"
                        class="lang-select__trigger"
                        aria-expanded="false"
                        aria-haspopup="listbox"
                        aria-controls="public-lang-menu"
                        aria-label="{{ __('public.lang.label') }}"
                        data-lang-select-trigger
                    >
                        <span class="lang-select__value" data-lang-select-value>{{ strtoupper($currentLocale) }}</span>
                        <span class="lang-select__chevron" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none">
                                <path d="M4 6.2 8 10l4-3.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </button>
                    <ul
                        id="public-lang-menu"
                        class="lang-select__menu"
                        role="listbox"
                        aria-label="{{ __('public.lang.choose') }}"
                    >
                        @foreach (\App\Support\Localization::availableLocales() as $locale)
                            <li role="presentation">
                                <a
                                    href="{{ $alternateUrls[$locale] ?? localized_route(Route::currentRouteName() ?? 'public.home', [], $locale) }}"
                                    class="lang-select__option"
                                    role="option"
                                    aria-selected="{{ $locale === $currentLocale ? 'true' : 'false' }}"
                                    hreflang="{{ $locale }}"
                                    data-lang="{{ $locale }}"
                                    data-lang-select-option
                                >
                                    <span>{{ \App\Support\Localization::label($locale) }}</span>
                                    <span class="lang-select__option-code">{{ strtoupper($locale) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <a
                    href="{{ url('/app/login') }}"
                    class="btn btn-secondary btn-sm layout-public__cabinet"
                    data-public-auth-cta="login"
                    data-label-login="{{ __('public.nav.login') }}"
                    data-label-authed="{{ __('public.nav.cabinet') }}"
                >{{ __('public.nav.login') }}</a>
                <a
                    href="{{ url('/app/register') }}"
                    class="btn btn-primary btn-sm layout-public__cabinet"
                    data-public-auth-cta="register"
                    data-label-register="{{ __('public.nav.register') }}"
                    data-label-authed="{{ __('public.nav.cabinet') }}"
                >{{ __('public.nav.register') }}</a>

                <button
                    class="layout-public__burger"
                    type="button"
                    aria-expanded="false"
                    aria-controls="public-nav"
                    aria-label="{{ __('public.nav.open_menu') }}"
                    data-public-nav-toggle
                >
                    <span class="layout-public__burger-lines" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
            </div>
        </div>

        <div
            id="public-nav"
            class="layout-public__panel"
            aria-hidden="true"
            data-public-nav-panel
        >
            <div class="container layout-public__panel-inner">
                <nav class="layout-public__nav layout-public__nav--mobile" aria-label="{{ __('public.nav.mobile') }}">
                    <ul class="layout-public__nav-list">
                        <li>
                            <a href="{{ localized_route('public.home') }}" class="layout-public__nav-link" data-public-nav-link>{{ __('public.nav.home') }}</a>
                        </li>
                        <li>
                            <a href="{{ localized_route('public.home') }}#how-it-works" class="layout-public__nav-link" data-public-nav-link>{{ __('public.nav.how_it_works') }}</a>
                        </li>
                        <li>
                            <a href="{{ localized_route('public.home') }}#pricing" class="layout-public__nav-link" data-public-nav-link>{{ __('public.nav.pricing') }}</a>
                        </li>
                    </ul>
                </nav>

                <div class="layout-public__panel-langs" data-lang-select>
                    <p class="layout-public__panel-lang-label">{{ __('public.lang.label') }}</p>
                    <ul class="layout-public__panel-lang-list" role="listbox" aria-label="{{ __('public.lang.choose') }}">
                        @foreach (\App\Support\Localization::availableLocales() as $locale)
                            <li role="presentation">
                                <a
                                    href="{{ $alternateUrls[$locale] ?? localized_route(Route::currentRouteName() ?? 'public.home', [], $locale) }}"
                                    class="layout-public__panel-lang-option {{ $locale === $currentLocale ? 'is-active' : '' }}"
                                    role="option"
                                    aria-selected="{{ $locale === $currentLocale ? 'true' : 'false' }}"
                                    hreflang="{{ $locale }}"
                                    data-lang="{{ $locale }}"
                                    data-lang-select-option
                                    data-public-nav-link
                                >
                                    <span>{{ \App\Support\Localization::label($locale) }}</span>
                                    <span>{{ strtoupper($locale) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="layout-public__panel-footer">
                    <a
                        href="{{ url('/app/login') }}"
                        class="btn btn-secondary w-100"
                        data-public-nav-link
                        data-public-auth-cta="login"
                        data-label-login="{{ __('public.nav.login') }}"
                        data-label-authed="{{ __('public.nav.cabinet') }}"
                    >{{ __('public.nav.login') }}</a>
                    <a
                        href="{{ url('/app/register') }}"
                        class="btn btn-primary w-100"
                        data-public-nav-link
                        data-public-auth-cta="register"
                        data-label-register="{{ __('public.nav.register') }}"
                        data-label-authed="{{ __('public.nav.cabinet') }}"
                    >{{ __('public.nav.register') }}</a>
                </div>
            </div>
        </div>
    </header>

    <div class="layout-public__backdrop" aria-hidden="true" data-public-nav-backdrop></div>

    <main class="layout-public__main">
        @yield('content')
    </main>

    <footer class="layout-public__footer">
        <div class="container layout-public__footer-inner">
            <div class="layout-public__footer-brand">
                <a href="{{ localized_route('public.home') }}" class="layout-public__footer-logo">
                    <img
                        src="{{ asset('images/logo.svg') }}"
                        alt="{{ config('app.name') }}"
                        width="80"
                        height="32"
                        decoding="async"
                    >
                </a>
                <p class="layout-public__footer-copy">
                    &copy; {{ date('Y') }} {{ config('app.name') }}
                </p>
            </div>

            <div class="layout-public__footer-menus">
                <nav class="layout-public__footer-col" aria-label="{{ __('public.nav.sections') }}">
                    <p class="layout-public__footer-label">{{ __('public.nav.sections') }}</p>
                    <ul class="layout-public__footer-list">
                        <li>
                            <a href="{{ localized_route('public.home') }}#how-it-works" class="layout-public__footer-link">{{ __('public.nav.how_it_works') }}</a>
                        </li>
                        <li>
                            <a href="{{ localized_route('public.home') }}#pricing" class="layout-public__footer-link">{{ __('public.nav.pricing') }}</a>
                        </li>
                        <li>
                            <a
                                href="{{ url('/app/login') }}"
                                class="layout-public__footer-link"
                                data-public-auth-cta="login"
                                data-label-login="{{ __('public.nav.login') }}"
                                data-label-authed="{{ __('public.nav.cabinet') }}"
                            >{{ __('public.nav.login') }}</a>
                        </li>
                    </ul>
                </nav>

                <nav class="layout-public__footer-col" aria-label="{{ __('public.nav.documents') }}">
                    <p class="layout-public__footer-label">{{ __('public.nav.documents') }}</p>
                    <ul class="layout-public__footer-list">
                        <li>
                            <a href="{{ localized_route('public.privacy') }}" class="layout-public__footer-link">{{ __('public.nav.privacy') }}</a>
                        </li>
                        <li>
                            <a href="{{ localized_route('public.terms') }}" class="layout-public__footer-link">{{ __('public.nav.terms') }}</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
