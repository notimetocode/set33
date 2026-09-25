<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', config('app.name'))">
    @stack('meta')
    @vite(['resources/scss/public.scss', 'resources/js/public/app.js'])
</head>
<body class="layout-public">
    <header class="layout-public__header" data-public-header>
        <div class="container layout-public__header-inner">
            <div class="layout-public__start">
                <a href="{{ route('public.home') }}" class="layout-public__brand">
                    <span class="logo">
                        <img class="logo__mark" src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" width="96" height="20">
                    </span>
                </a>

                <nav class="layout-public__nav layout-public__nav--desktop" aria-label="Основная навигация">
                    <ul class="layout-public__nav-list">
                        <li>
                            <a href="{{ route('public.home') }}#how-it-works" class="layout-public__nav-link">Как это работает</a>
                        </li>
                        <li>
                            <a href="{{ route('public.home') }}#pricing" class="layout-public__nav-link">Цены</a>
                        </li>
                    </ul>
                </nav>
            </div>

            <div class="layout-public__actions">
                <a
                    href="{{ url('/app/login') }}"
                    class="btn btn-secondary btn-sm layout-public__cabinet"
                    data-public-auth-cta="login"
                >Войти</a>
                <a
                    href="{{ url('/app/register') }}"
                    class="btn btn-primary btn-sm layout-public__cabinet"
                    data-public-auth-cta="register"
                >Зарегистрироваться</a>

                <button
                    class="layout-public__burger"
                    type="button"
                    aria-expanded="false"
                    aria-controls="public-nav"
                    aria-label="Открыть меню"
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
                <nav class="layout-public__nav layout-public__nav--mobile" aria-label="Мобильная навигация">
                    <ul class="layout-public__nav-list">
                        <li>
                            <a href="{{ route('public.home') }}" class="layout-public__nav-link" data-public-nav-link>Главная</a>
                        </li>
                        <li>
                            <a href="{{ route('public.home') }}#how-it-works" class="layout-public__nav-link" data-public-nav-link>Как это работает</a>
                        </li>
                        <li>
                            <a href="{{ route('public.home') }}#pricing" class="layout-public__nav-link" data-public-nav-link>Цены</a>
                        </li>
                    </ul>
                </nav>

                <div class="layout-public__panel-footer">
                    <a
                        href="{{ url('/app/login') }}"
                        class="btn btn-secondary w-100"
                        data-public-nav-link
                        data-public-auth-cta="login"
                    >Войти</a>
                    <a
                        href="{{ url('/app/register') }}"
                        class="btn btn-primary w-100"
                        data-public-nav-link
                        data-public-auth-cta="register"
                    >Зарегистрироваться</a>
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
            <p class="layout-public__footer-copy">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </p>

            <nav class="layout-public__footer-nav" aria-label="Навигация в подвале">
                <a href="{{ route('public.home') }}#how-it-works" class="layout-public__footer-link">Как это работает</a>
                <a href="{{ route('public.home') }}#pricing" class="layout-public__footer-link">Цены</a>
                <a href="{{ route('public.privacy') }}" class="layout-public__footer-link">Политика конфиденциальности</a>
                <a href="{{ route('public.terms') }}" class="layout-public__footer-link">Условия использования</a>
                <a
                    href="{{ url('/app/login') }}"
                    class="layout-public__footer-link"
                    data-public-auth-cta="login"
                >Войти</a>
            </nav>
        </div>
    </footer>

    <x-cookie-consent />
</body>
</html>
