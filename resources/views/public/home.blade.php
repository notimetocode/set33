@extends('public.layouts.app')

@section('title', config('app.name').' — AI анализ SEO сайта')
@section('meta_description', 'AI-отчёты по сайту на основе Google Analytics, Search Console, GitHub и других сервисов. Подключите источники — получайте понятные выводы по трафику, поиску и релизам.')

@section('content')
    <div class="page-home">
        <section class="page-home__hero" aria-labelledby="home-hero-heading">
            <div class="page-home__hero-bg" aria-hidden="true"></div>

            <canvas
                class="page-home__torus"
                data-hero-torus
                width="640"
                height="640"
                aria-hidden="true"
            ></canvas>

            <div class="container page-home__hero-grid">
                <div class="page-home__hero-inner">
                    <img
                        class="page-home__brand"
                        src="{{ asset('images/logo.svg') }}"
                        alt="{{ config('app.name') }}"
                        width="364"
                        height="76"
                    >

                    <h1 id="home-hero-heading" class="page-home__headline">
                        AI анализ SEO сайта
                    </h1>

                    <p class="page-home__lead">
                        AI-отчёты по сайту на основе Google Analytics, Search Console, GitHub и других сервисов. Подключите источники — получайте понятные выводы по трафику, поиску и релизам.
                    </p>

                    <div class="page-home__cta">
                        <a
                            href="{{ url('/app/register') }}"
                            class="btn btn-primary btn-lg"
                            data-public-auth-cta="register"
                            data-public-auth-keep
                        >Зарегистрироваться</a>
                        <a href="#how-it-works" class="btn btn-secondary btn-lg">
                            Как это работает
                            <span class="page-home__cta-arrow" aria-hidden="true">→</span>
                        </a>
                    </div>
                </div>

                <div
                    class="page-home__hero-visual"
                    data-hero-torus-hit
                    aria-hidden="true"
                >
                    <span class="page-home__torus-word page-home__torus-word--data" data-torus-word="data">Данные</span>
                    <span class="page-home__torus-word page-home__torus-word--chaos" data-torus-word="chaos">Хаос</span>
                </div>
            </div>
        </section>

        <section
            id="how-it-works"
            class="page-home__section page-home__section--how"
            aria-labelledby="how-heading"
        >
            <div class="container">
                <header class="page-home__section-intro">
                    <p class="page-home__section-eyebrow">Процесс</p>
                    <h2 id="how-heading" class="page-home__section-title">Как это работает</h2>
                    <p class="page-home__section-lead">
                        Три шага от подключения источников до готового AI-отчёта
                    </p>
                </header>

                <ol class="page-home__steps">
                    <li class="page-home__step">
                        <div class="page-home__step-visual" aria-hidden="true">
                            <svg class="page-home__step-svg" viewBox="0 0 240 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M48 72V88L120 112M120 72V112M192 72V88L120 112" class="page-home__step-svg-link" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                <rect x="18" y="18" width="60" height="54" rx="12" class="page-home__step-svg-panel"/>
                                <rect x="90" y="18" width="60" height="54" rx="12" class="page-home__step-svg-panel page-home__step-svg-panel--accent"/>
                                <rect x="162" y="18" width="60" height="54" rx="12" class="page-home__step-svg-panel"/>
                                <circle cx="48" cy="36" r="5.5" class="page-home__step-svg-dot"/>
                                <circle cx="120" cy="36" r="5.5" class="page-home__step-svg-dot page-home__step-svg-dot--mint"/>
                                <circle cx="192" cy="36" r="5.5" class="page-home__step-svg-dot"/>
                                <text x="48" y="56" text-anchor="middle" class="page-home__step-svg-label">GA4</text>
                                <text x="120" y="56" text-anchor="middle" class="page-home__step-svg-label">GSC</text>
                                <text x="192" y="56" text-anchor="middle" class="page-home__step-svg-label">GitHub</text>
                                <rect x="78" y="112" width="84" height="28" rx="10" class="page-home__step-svg-panel page-home__step-svg-panel--soft"/>
                                <text x="120" y="130" text-anchor="middle" class="page-home__step-svg-label">Сайт</text>
                            </svg>
                        </div>
                        <span class="page-home__step-num" aria-hidden="true">01</span>
                        <div class="page-home__step-body">
                            <h3 class="page-home__step-title">Подключите источники</h3>
                            <p class="page-home__step-text">
                                Добавьте сайт и свяжите Google Analytics, Search Console и GitHub
                            </p>
                        </div>
                    </li>
                    <li class="page-home__step">
                        <div class="page-home__step-visual" aria-hidden="true">
                            <svg class="page-home__step-svg" viewBox="0 0 240 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="28" y="24" width="184" height="112" rx="14" class="page-home__step-svg-panel"/>
                                <path d="M48 108V84M72 108V64M96 108V92M120 108V52M144 108V70M168 108V44M192 108V78" class="page-home__step-svg-bar" stroke-width="10" stroke-linecap="round"/>
                                <path d="M48 96C72 96 72 58 96 58C120 58 120 88 144 72C168 56 168 40 192 48" class="page-home__step-svg-trend" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="192" cy="48" r="5" class="page-home__step-svg-dot page-home__step-svg-dot--mint"/>
                                <rect x="44" y="34" width="40" height="8" rx="4" class="page-home__step-svg-chip"/>
                                <rect x="92" y="34" width="28" height="8" rx="4" class="page-home__step-svg-chip page-home__step-svg-chip--mint"/>
                            </svg>
                        </div>
                        <span class="page-home__step-num" aria-hidden="true">02</span>
                        <div class="page-home__step-body">
                            <h3 class="page-home__step-title">Соберите данные</h3>
                            <p class="page-home__step-text">
                                Синхронизируйте метрики и зафиксируйте важные события по сайту
                            </p>
                        </div>
                    </li>
                    <li class="page-home__step">
                        <div class="page-home__step-visual" aria-hidden="true">
                            <svg class="page-home__step-svg" viewBox="0 0 240 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="52" y="20" width="136" height="120" rx="14" class="page-home__step-svg-panel page-home__step-svg-panel--accent"/>
                                <rect x="68" y="38" width="72" height="8" rx="4" class="page-home__step-svg-chip"/>
                                <rect x="68" y="56" width="104" height="6" rx="3" class="page-home__step-svg-line"/>
                                <rect x="68" y="70" width="88" height="6" rx="3" class="page-home__step-svg-line"/>
                                <rect x="68" y="84" width="96" height="6" rx="3" class="page-home__step-svg-line"/>
                                <rect x="68" y="106" width="48" height="18" rx="6" class="page-home__step-svg-panel page-home__step-svg-panel--soft"/>
                                <path d="M168 34l4.5 9.5L183 48l-9.5 4.5L168 62l-4.5-9.5L154 48l9.5-4.5L168 34z" class="page-home__step-svg-spark"/>
                                <circle cx="176" cy="112" r="18" class="page-home__step-svg-glow"/>
                                <text x="176" y="117" text-anchor="middle" class="page-home__step-svg-ai">AI</text>
                            </svg>
                        </div>
                        <span class="page-home__step-num" aria-hidden="true">03</span>
                        <div class="page-home__step-body">
                            <h3 class="page-home__step-title">Получите отчёт</h3>
                            <p class="page-home__step-text">
                                Сгенерируйте AI-отчёт за выбранный период с выводами и рекомендациями
                            </p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <section
            id="pricing"
            class="page-home__section page-home__section--pricing"
            aria-labelledby="pricing-heading"
        >
            <div class="container">
                <header class="page-home__section-intro">
                    <h2 id="pricing-heading" class="page-home__section-title">Цены</h2>
                    <p class="page-home__section-lead">
                        Выберите объём отчётов под задачу — от разового анализа до агентского потока
                    </p>
                </header>

                <div class="page-home__plans">
                    <article class="page-home__plan">
                        <header class="page-home__plan-head">
                            <h3 class="page-home__plan-name">Стандарт</h3>
                            <p class="page-home__plan-price">
                                <span class="page-home__plan-amount">$10</span>
                            </p>
                            <p class="page-home__plan-desc">Разовый отчёт по одному сайту</p>
                        </header>
                        <ul class="page-home__plan-features">
                            <li>1 AI-отчёт</li>
                            <li>1 сайт</li>
                            <li>Analytics, Search Console и GitHub</li>
                        </ul>
                        <a href="{{ url('/app/login') }}" class="btn btn-secondary w-100">Начать</a>
                    </article>

                    <article class="page-home__plan page-home__plan--featured">
                        <p class="page-home__plan-badge">Рекомендуем</p>
                        <header class="page-home__plan-head">
                            <h3 class="page-home__plan-name">Про</h3>
                            <p class="page-home__plan-price">
                                <span class="page-home__plan-amount">$80</span>
                                <span class="page-home__plan-period">/ мес</span>
                            </p>
                            <p class="page-home__plan-desc">Регулярные отчёты для нескольких проектов</p>
                        </header>
                        <ul class="page-home__plan-features">
                            <li>10 отчётов в месяц по расписанию</li>
                            <li>Несколько сайтов</li>
                            <li>Analytics, Search Console и GitHub</li>
                            <li>События и история отчётов</li>
                        </ul>
                        <a href="{{ url('/app/login') }}" class="btn btn-primary w-100">Начать</a>
                    </article>

                    <article class="page-home__plan">
                        <header class="page-home__plan-head">
                            <h3 class="page-home__plan-name">Агентство</h3>
                            <p class="page-home__plan-price">
                                <span class="page-home__plan-amount">$600</span>
                                <span class="page-home__plan-period">/ мес</span>
                            </p>
                            <p class="page-home__plan-desc">Масштаб для команд и клиентских портфелей</p>
                        </header>
                        <ul class="page-home__plan-features">
                            <li>100 отчётов в месяц по расписанию</li>
                            <li>Несколько сайтов</li>
                            <li>Выбор AI-модели</li>
                            <li>Интеграция с проектом по API</li>
                            <li>Analytics, Search Console и GitHub</li>
                        </ul>
                        <a href="{{ url('/app/login') }}" class="btn btn-secondary w-100">Начать</a>
                    </article>
                </div>
            </div>
        </section>
    </div>
@endsection
