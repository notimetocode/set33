@extends('public.layouts.app')

@section('title', config('app.name').' — AI-отчёты по SEO сайта')
@section('meta_description', 'AI-отчёты по сайту на основе Google Analytics, Search Console, GitHub и других сервисов. Подключите источники — получайте понятные выводы по трафику, поиску и релизам.')

@section('content')
    <div class="page-home" data-home-tabs>
        <section class="page-home__hero" aria-labelledby="home-hero-heading">
            <div class="page-home__dots" aria-hidden="true"></div>

            <div class="container page-home__hero-inner">
                <h1 id="home-hero-heading" class="page-home__headline">
                    ИИ-аналитика SEO сайта
                </h1>
                <p class="page-home__lead">
                    ИИ-отчёты на основе Google Analytics, Search Console, PageSpeed, GitHub и&nbsp;др.
                    Выводы по трафику, поиску, динамике и&nbsp;релизам без ручной сводки.
                </p>
                <div class="page-home__cta">
                    <a href="#how-it-works" class="btn btn-secondary btn-lg">
                        Как это работает
                    </a>
                    <a
                        href="{{ url('/app/register') }}"
                        class="btn btn-primary btn-lg"
                        data-public-auth-cta="register"
                        data-public-auth-keep
                    >Попробовать</a>
                </div>
            </div>
        </section>

        <section
            id="how-it-works"
            class="page-home__features"
            aria-labelledby="how-heading"
        >
            <div class="page-home__dots page-home__dots--flat" aria-hidden="true"></div>

            <div class="page-home__features-track">
                <div
                    class="page-home__tabs"
                    role="tablist"
                    aria-label="Возможности продукта"
                >
                    <button
                        type="button"
                        class="page-home__tab is-active"
                        role="tab"
                        id="home-tab-sources"
                        data-home-tab="sources"
                        aria-selected="true"
                        tabindex="0"
                    >
                        <span class="page-home__tab-title">Автоматический сбор данных</span>
                        <span class="page-home__tab-caption">GA, GSC, PageSpeed, GitHub</span>
                    </button>
                    <button
                        type="button"
                        class="page-home__tab"
                        role="tab"
                        id="home-tab-metrics"
                        data-home-tab="metrics"
                        aria-selected="false"
                        tabindex="-1"
                    >
                        <span class="page-home__tab-title">Метрики и события</span>
                        <span class="page-home__tab-caption">полный контекст периода</span>
                    </button>
                    <button
                        type="button"
                        class="page-home__tab"
                        role="tab"
                        id="home-tab-reports"
                        data-home-tab="reports"
                        aria-selected="false"
                        tabindex="-1"
                    >
                        <span class="page-home__tab-title">AI-отчёты</span>
                        <span class="page-home__tab-caption">выводы, а не сырые цифры</span>
                    </button>
                    <button
                        type="button"
                        class="page-home__tab"
                        role="tab"
                        id="home-tab-sites"
                        data-home-tab="sites"
                        aria-selected="false"
                        tabindex="-1"
                    >
                        <span class="page-home__tab-title">Гибкая настройка</span>
                        <span class="page-home__tab-caption">под сайт и задачу</span>
                    </button>
                </div>
            </div>

            <div class="container page-home__bento">
                <div class="page-home__bento-panel">
                    <header class="page-home__bento-intro">
                        <h2 id="how-heading" class="page-home__bento-title">
                            Готовая система, а не шаблон
                        </h2>
                        <p class="page-home__bento-lead">
                            Подключаем сервисы, события и документы сайта —
                            в отчёт попадает то, что реально влияет на SEO.
                        </p>
                    </header>

                    <div class="page-home__bento-grid" role="list">
                        <article class="page-home__bento-cell page-home__bento-cell--sm" role="listitem">
                            <div class="page-home__bento-source-head">
                                <img
                                    class="page-home__bento-source-logo"
                                    src="{{ asset('images/integrations/google-analytics.svg') }}"
                                    alt=""
                                    width="24"
                                    height="24"
                                    decoding="async"
                                >
                                <h3 class="page-home__bento-cell-title">Google Analytics</h3>
                            </div>
                            <p class="page-home__bento-source-data">
                                Сессии, пользователи, просмотры и органический трафик
                                с вовлечённостью по дням.
                            </p>
                            <p class="page-home__bento-cell-text">
                                Видно, как меняется поведение аудитории после SEO-работ —
                                не только визиты, но и качество трафика.
                            </p>
                        </article>

                        <article class="page-home__bento-cell page-home__bento-cell--lg" role="listitem">
                            <div class="page-home__bento-source-head">
                                <img
                                    class="page-home__bento-source-logo"
                                    src="{{ asset('images/integrations/google-search-console.svg') }}"
                                    alt=""
                                    width="24"
                                    height="24"
                                    decoding="async"
                                >
                                <h3 class="page-home__bento-cell-title">Search Console</h3>
                            </div>
                            <p class="page-home__bento-source-data">
                                Клики, показы, CTR, позиции; топ-запросы и страницы;
                                устройства, страны, sitemaps и URL Inspection.
                            </p>
                            <p class="page-home__bento-cell-text">
                                Прямые данные поиска Google: видимость, индексация
                                и запросы, по которым вас находят.
                            </p>
                        </article>

                        <article class="page-home__bento-cell page-home__bento-cell--lg" role="listitem">
                            <div class="page-home__bento-source-head">
                                <img
                                    class="page-home__bento-source-logo"
                                    src="{{ asset('images/integrations/github.svg') }}"
                                    alt=""
                                    width="24"
                                    height="24"
                                    decoding="async"
                                >
                                <h3 class="page-home__bento-cell-title">GitHub</h3>
                            </div>
                            <p class="page-home__bento-source-data">
                                Коммиты за период: сообщения, авторы и даты
                                из привязанного репозитория.
                            </p>
                            <p class="page-home__bento-cell-text">
                                Связывает релизы и правки кода со скачками метрик —
                                что в разработке могло повлиять на SEO.
                            </p>
                        </article>

                        <article class="page-home__bento-cell page-home__bento-cell--sm" role="listitem">
                            <div class="page-home__bento-source-head">
                                <img
                                    class="page-home__bento-source-logo"
                                    src="{{ asset('images/integrations/pagespeed.svg') }}"
                                    alt=""
                                    width="24"
                                    height="24"
                                    decoding="async"
                                >
                                <h3 class="page-home__bento-cell-title">PageSpeed / CrUX</h3>
                            </div>
                            <p class="page-home__bento-source-data">
                                Core Web Vitals (LCP, INP, CLS), оценки Lighthouse
                                и полевые данные Chrome UX Report.
                            </p>
                            <p class="page-home__bento-cell-text">
                                Скорость и стабильность — фактор ранжирования и UX;
                                без них SEO-отчёт неполный.
                            </p>
                        </article>

                        <article class="page-home__bento-cell page-home__bento-cell--full" role="listitem">
                            <div class="page-home__bento-wide-copy">
                                <h3 class="page-home__bento-cell-title">События и документы</h3>
                                <p class="page-home__bento-source-data">
                                    Ручные заметки за период и Markdown-файлы сайта:
                                    публикации, акции, инциденты, брендбук, ТЗ, семантика.
                                </p>
                                <p class="page-home__bento-cell-text">
                                    Внешние факторы объясняют скачки метрик, а документы
                                    дают AI рамку продукта — рекомендации не из шаблона.
                                </p>
                            </div>
                            <div class="page-home__bento-wide-illus" aria-hidden="true">
                                <div class="page-home__bento-stack">
                                    <div class="page-home__bento-mini page-home__bento-mini--event">
                                        <span class="page-home__bento-mini-label">Событие</span>
                                        <span class="page-home__bento-mini-title">Запуск раздела блога</span>
                                        <span class="page-home__bento-mini-meta">12 сен · ссылка</span>
                                    </div>
                                    <div class="page-home__bento-mini page-home__bento-mini--doc">
                                        <span class="page-home__bento-mini-label">Документ</span>
                                        <span class="page-home__bento-mini-title">Семантика · brand.md</span>
                                        <span class="page-home__bento-mini-lines">
                                            <span></span><span></span><span></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="outcome"
            class="page-home__outcome"
            aria-labelledby="outcome-heading"
        >
            <div class="page-home__dots page-home__dots--flat" aria-hidden="true"></div>

            <div class="container page-home__outcome-inner">
                <div class="page-home__outcome-panel">
                    <div class="page-home__outcome-copy">
                        <h2 id="outcome-heading" class="page-home__outcome-title">
                            Что получаете на выходе
                        </h2>
                        <p class="page-home__outcome-lead">
                            Не дашборд с десятками графиков, а готовый AI-отчёт:
                            источники, метрики, события и документы сайта собираются
                            в одну картину — с выводами, что изменилось и что делать дальше.
                        </p>
                        <ul class="page-home__outcome-points">
                            <li>
                                Понятные выводы по трафику, поиску и релизам:
                                что выросло, что просело и какие факторы на это влияют.
                            </li>
                            <li>
                                Рекомендации под ваш продукт — AI опирается на события
                                и документы сайта, а не на общие шаблоны.
                            </li>
                        </ul>
                    </div>

                    <div class="page-home__outcome-visual" aria-hidden="true">
                        <div class="page-home__flow">
                            <div class="page-home__flow-in">
                                <div class="page-home__flow-sources">
                                    <span class="page-home__flow-source">
                                        <img
                                            src="{{ asset('images/integrations/google-analytics.svg') }}"
                                            alt=""
                                            width="22"
                                            height="22"
                                            decoding="async"
                                        >
                                    </span>
                                    <span class="page-home__flow-source">
                                        <img
                                            src="{{ asset('images/integrations/google-search-console.svg') }}"
                                            alt=""
                                            width="22"
                                            height="22"
                                            decoding="async"
                                        >
                                    </span>
                                    <span class="page-home__flow-source">
                                        <img
                                            src="{{ asset('images/integrations/github.svg') }}"
                                            alt=""
                                            width="22"
                                            height="22"
                                            decoding="async"
                                        >
                                    </span>
                                    <span class="page-home__flow-source">
                                        <img
                                            src="{{ asset('images/integrations/pagespeed.svg') }}"
                                            alt=""
                                            width="22"
                                            height="22"
                                            decoding="async"
                                        >
                                    </span>
                                </div>

                                <svg
                                    class="page-home__flow-fan"
                                    viewBox="0 0 120 100"
                                    preserveAspectRatio="none"
                                    focusable="false"
                                >
                                    <path class="page-home__flow-fan-line" d="M0 10.5 H48 L120 50"/>
                                    <path class="page-home__flow-fan-line" d="M0 36.8 H48 L120 50"/>
                                    <path class="page-home__flow-fan-line" d="M0 63.2 H48 L120 50"/>
                                    <path class="page-home__flow-fan-line" d="M0 89.5 H48 L120 50"/>
                                    <path class="page-home__flow-fan-pulse" d="M0 10.5 H48 L120 50" pathLength="100"/>
                                    <path class="page-home__flow-fan-pulse" d="M0 36.8 H48 L120 50" pathLength="100"/>
                                    <path class="page-home__flow-fan-pulse" d="M0 63.2 H48 L120 50" pathLength="100"/>
                                    <path class="page-home__flow-fan-pulse" d="M0 89.5 H48 L120 50" pathLength="100"/>
                                </svg>
                            </div>

                            <div class="page-home__flow-hub">
                                <span class="page-home__flow-hub-ring"></span>
                                <span class="page-home__flow-hub-ring"></span>
                                <span class="page-home__flow-hub-core">
                                    <span class="page-home__flow-chip">
                                        <span class="page-home__flow-chip-body">AI</span>
                                    </span>
                                </span>
                            </div>

                            <div class="page-home__flow-rail page-home__flow-rail--out">
                                <span class="page-home__flow-rail-line"></span>
                                <span class="page-home__flow-rail-pulse"></span>
                            </div>

                            <div class="page-home__flow-result">
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" aria-hidden="true">
                                    <path
                                        d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linejoin="round"
                                    />
                                    <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                    <path d="M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="pricing"
            class="page-home__pricing"
            aria-labelledby="pricing-heading"
        >
            <div class="page-home__dots page-home__dots--flat" aria-hidden="true"></div>

            <div class="container page-home__pricing-inner">
                <div class="page-home__pricing-panel">
                    <header class="page-home__pricing-intro">
                        <h2 id="pricing-heading" class="page-home__pricing-title">Цены</h2>
                        <p class="page-home__pricing-lead">
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
            </div>
        </section>
    </div>
@endsection
