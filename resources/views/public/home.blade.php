@extends('public.layouts.app')

@section('title', config('app.name'))
@section('meta_description', 'Публичный сайт и личный кабинет')

@section('content')
    <section class="page-home__hero">
        <div class="page-home__hero-bg" aria-hidden="true"></div>

        <div class="container page-home__hero-grid">
            <div class="page-home__hero-copy">
                <p class="page-home__badge">
                    <span class="page-home__badge-dot" aria-hidden="true"></span>
                    {{ config('app.name') }}
                </p>

                <h1 class="page-home__headline">
                    Публичный сайт, личный кабинет и панель администратора
                </h1>

                <p class="page-home__lead">
                    Каркас проекта готов. Дальше можно добавлять предметную логику поверх этой структуры.
                </p>

                <div class="page-home__cta">
                    <a href="{{ url('/app') }}" class="btn btn-primary btn-lg">Личный кабинет</a>
                    <a href="{{ url('/admin') }}" class="btn btn-secondary btn-lg">
                        Админ-панель
                        <span class="page-home__cta-arrow" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
