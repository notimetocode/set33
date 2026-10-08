@extends('public.layouts.app')

@section('title', __('public.home.title', ['app' => config('app.name')]))
@section('meta_description', __('public.home.meta_description'))

@section('content')
    <div class="page-home" data-home-tabs>
        <section class="page-home__hero" aria-labelledby="home-hero-heading">
            <div class="page-home__dots" aria-hidden="true"></div>

            <div class="container page-home__hero-inner">
                <h1 id="home-hero-heading" class="page-home__headline">
                    {{ __('public.home.hero_title') }}
                </h1>
                <p class="page-home__lead">
                    {{ __('public.home.hero_lead') }}
                </p>
                <form
                    class="page-home__audit-card"
                    action="{{ localized_route('public.site-audit.show') }}"
                    method="get"
                >
                    <label class="page-home__audit-caption" for="home-site-audit-url">
                        {{ __('public.home.audit_caption') }}
                        <span class="page-home__audit-caption-accent">{{ __('public.home.audit_no_registration') }}</span>
                    </label>
                    <div class="page-home__audit-row">
                        <input
                            id="home-site-audit-url"
                            class="form-control form-control-lg page-home__audit-input"
                            type="text"
                            name="url"
                            required
                            maxlength="2048"
                            autocomplete="url"
                            inputmode="url"
                            placeholder="{{ __('public.home.audit_placeholder') }}"
                        >
                        <button
                            type="submit"
                            class="btn btn-primary btn-lg page-home__audit-submit"
                        >
                            {{ __('public.home.audit_submit') }}
                        </button>
                    </div>
                </form>
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
                    aria-label="{{ __('public.home.tabs_label') }}"
                    data-home-draw="frame"
                    data-home-draw-rail
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
                        <span class="page-home__tab-title">{{ __('public.home.tab_sources_title') }}</span>
                        <span class="page-home__tab-caption">{{ __('public.home.tab_sources_caption') }}</span>
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
                        <span class="page-home__tab-title">{{ __('public.home.tab_metrics_title') }}</span>
                        <span class="page-home__tab-caption">{{ __('public.home.tab_metrics_caption') }}</span>
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
                        <span class="page-home__tab-title">{{ __('public.home.tab_reports_title') }}</span>
                        <span class="page-home__tab-caption">{{ __('public.home.tab_reports_caption') }}</span>
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
                        <span class="page-home__tab-title">{{ __('public.home.tab_sites_title') }}</span>
                        <span class="page-home__tab-caption">{{ __('public.home.tab_sites_caption') }}</span>
                    </button>
                </div>
            </div>

            <div class="container page-home__bento" data-home-draw-rail>
                <div class="page-home__bento-panel" data-home-draw="frame">
                    <header class="page-home__bento-intro">
                        <h2 id="how-heading" class="page-home__bento-title">
                            {{ __('public.home.bento_title') }}
                        </h2>
                        <p class="page-home__bento-lead">
                            {{ __('public.home.bento_lead') }}
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
                                {{ __('public.home.ga_data') }}
                            </p>
                            <p class="page-home__bento-cell-text">
                                {{ __('public.home.ga_text') }}
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
                                {{ __('public.home.gsc_data') }}
                            </p>
                            <p class="page-home__bento-cell-text">
                                {{ __('public.home.gsc_text') }}
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
                                {{ __('public.home.github_data') }}
                            </p>
                            <p class="page-home__bento-cell-text">
                                {{ __('public.home.github_text') }}
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
                                <h3 class="page-home__bento-cell-title">Chrome UX Report</h3>
                            </div>
                            <p class="page-home__bento-source-data">
                                {{ __('public.home.crux_data') }}
                            </p>
                            <p class="page-home__bento-cell-text">
                                {{ __('public.home.crux_text') }}
                            </p>
                        </article>

                        <article class="page-home__bento-cell page-home__bento-cell--full" role="listitem">
                            <div class="page-home__bento-wide-copy">
                                <h3 class="page-home__bento-cell-title">{{ __('public.home.events_title') }}</h3>
                                <p class="page-home__bento-source-data">
                                    {{ __('public.home.events_data') }}
                                </p>
                                <p class="page-home__bento-cell-text">
                                    {{ __('public.home.events_text') }}
                                </p>
                            </div>
                            <div class="page-home__bento-wide-illus" aria-hidden="true">
                                <div class="page-home__bento-stack">
                                    <div class="page-home__bento-mini page-home__bento-mini--event">
                                        <span class="page-home__bento-mini-label">{{ __('public.home.mini_event_label') }}</span>
                                        <span class="page-home__bento-mini-title">{{ __('public.home.mini_event_title') }}</span>
                                        <span class="page-home__bento-mini-meta">{{ __('public.home.mini_event_meta') }}</span>
                                    </div>
                                    <div class="page-home__bento-mini page-home__bento-mini--doc">
                                        <span class="page-home__bento-mini-label">{{ __('public.home.mini_doc_label') }}</span>
                                        <span class="page-home__bento-mini-title">{{ __('public.home.mini_doc_title') }}</span>
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

            <div class="container page-home__outcome-inner" data-home-draw-rail>
                <div class="page-home__outcome-panel" data-home-draw="frame">
                    <div class="page-home__outcome-copy">
                        <h2 id="outcome-heading" class="page-home__outcome-title">
                            {{ __('public.home.outcome_title') }}
                        </h2>
                        <p class="page-home__outcome-lead">
                            {{ __('public.home.outcome_lead') }}
                        </p>
                        <ul class="page-home__outcome-points">
                            <li>{{ __('public.home.outcome_point_1') }}</li>
                            <li>{{ __('public.home.outcome_point_2') }}</li>
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
                <div class="page-home__pricing-panel" data-home-draw="frame">
                    <header class="page-home__pricing-intro">
                        <h2 id="pricing-heading" class="page-home__pricing-title">{{ __('public.home.pricing_title') }}</h2>
                        <p class="page-home__pricing-lead">
                            {{ __('public.home.pricing_lead') }}
                        </p>
                    </header>

                    <div class="page-home__plans">
                        <article class="page-home__plan">
                            <header class="page-home__plan-head">
                                <h3 class="page-home__plan-name">{{ __('public.home.plan_standard') }}</h3>
                                <p class="page-home__plan-price">
                                    <span class="page-home__plan-amount">$10</span>
                                </p>
                                <p class="page-home__plan-desc">{{ __('public.home.plan_standard_desc') }}</p>
                            </header>
                            <ul class="page-home__plan-features">
                                <li>{{ __('public.home.feature_reports_10') }}</li>
                                <li>{{ __('public.home.feature_ga') }}</li>
                                <li>{{ __('public.home.feature_gsc') }}</li>
                                <li>{{ __('public.home.feature_github') }}</li>
                                <li>{{ __('public.home.feature_crux') }}</li>
                                <li>{{ __('public.home.feature_events') }}</li>
                                <li>{{ __('public.home.feature_documents') }}</li>
                            </ul>
                            <a href="{{ url('/app/login') }}" class="btn btn-secondary w-100">{{ __('public.home.plan_start') }}</a>
                        </article>

                        <article class="page-home__plan page-home__plan--featured">
                            <p class="page-home__plan-badge">{{ __('public.home.plan_badge') }}</p>
                            <header class="page-home__plan-head">
                                <h3 class="page-home__plan-name">{{ __('public.home.plan_pro') }}</h3>
                                <p class="page-home__plan-price">
                                    <span class="page-home__plan-amount">$80</span>
                                    <span class="page-home__plan-period">{{ __('public.home.plan_per_month') }}</span>
                                </p>
                                <p class="page-home__plan-desc">{{ __('public.home.plan_pro_desc') }}</p>
                            </header>
                            <ul class="page-home__plan-features">
                                <li>{{ __('public.home.feature_reports_100') }}</li>
                                <li>{{ __('public.home.feature_ai_model') }}</li>
                                <li>{{ __('public.home.feature_ga') }}</li>
                                <li>{{ __('public.home.feature_gsc') }}</li>
                                <li>{{ __('public.home.feature_github') }}</li>
                                <li>{{ __('public.home.feature_crux') }}</li>
                                <li>{{ __('public.home.feature_events') }}</li>
                                <li>{{ __('public.home.feature_documents') }}</li>
                            </ul>
                            <a href="{{ url('/app/login') }}" class="btn btn-primary w-100">{{ __('public.home.plan_start') }}</a>
                        </article>

                        <article class="page-home__plan">
                            <header class="page-home__plan-head">
                                <h3 class="page-home__plan-name">{{ __('public.home.plan_agency') }}</h3>
                                <p class="page-home__plan-price">
                                    <span class="page-home__plan-amount">$600</span>
                                    <span class="page-home__plan-period">{{ __('public.home.plan_per_month') }}</span>
                                </p>
                                <p class="page-home__plan-desc">{{ __('public.home.plan_agency_desc') }}</p>
                            </header>
                            <ul class="page-home__plan-features">
                                <li>{{ __('public.home.feature_reports_1000') }}</li>
                                <li>{{ __('public.home.feature_ai_model') }}</li>
                                <li>{{ __('public.home.feature_custom_models') }}</li>
                                <li>{{ __('public.home.feature_ga') }}</li>
                                <li>{{ __('public.home.feature_gsc') }}</li>
                                <li>{{ __('public.home.feature_github') }}</li>
                                <li>{{ __('public.home.feature_crux') }}</li>
                                <li>{{ __('public.home.feature_events') }}</li>
                                <li>{{ __('public.home.feature_documents') }}</li>
                            </ul>
                            <a href="{{ url('/app/login') }}" class="btn btn-secondary w-100">{{ __('public.home.plan_start') }}</a>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
