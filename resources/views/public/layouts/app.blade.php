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
    <header class="layout-public__header">
        <div class="container layout-public__header-inner">
            <div class="layout-public__start">
                <a href="{{ route('public.home') }}" class="layout-public__brand">
                    <span class="logo">
                        <img class="logo__mark" src="{{ asset('images/logo.svg') }}" alt="" width="32" height="32">
                        <span class="logo__name">{{ config('app.name') }}</span>
                    </span>
                </a>
            </div>

            <div class="layout-public__actions">
                <a
                    href="{{ url('/app/login') }}"
                    class="btn btn-primary btn-sm layout-public__cabinet"
                    data-public-auth-cta
                >Войти</a>

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
    </header>

    <div class="layout-public__backdrop" aria-hidden="true" data-public-nav-backdrop></div>

    <aside
        id="public-nav"
        class="layout-public__sidebar"
        aria-hidden="true"
        data-public-nav-sidebar
    >
        <nav class="layout-public__nav" aria-label="Мобильная навигация">
            <a href="{{ route('public.home') }}" class="layout-public__nav-link" data-public-nav-link>Главная</a>
        </nav>

        <div class="layout-public__sidebar-footer">
            <a
                href="{{ url('/app/login') }}"
                class="btn btn-primary w-100"
                data-public-nav-link
                data-public-auth-cta
            >Войти</a>
        </div>
    </aside>

    <main class="layout-public__main">
        @yield('content')
    </main>

    <footer class="layout-public__footer">
        <div class="container">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
        </div>
    </footer>

    <x-cookie-consent />
</body>
</html>
