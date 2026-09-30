@extends('public.layouts.app')

@section('title', __('public.legal.privacy_meta_title', ['app' => config('app.name')]))
@section('meta_description', __('public.legal.privacy_meta_description', ['app' => config('app.name')]))

@section('content')
    <article class="page-legal">
        <section class="page-legal__hero" aria-labelledby="privacy-heading">
            <div class="page-legal__dots" aria-hidden="true"></div>

            <div class="container page-legal__hero-inner">
                <p class="page-legal__eyebrow">{{ __('public.legal.eyebrow') }}</p>
                <h1 id="privacy-heading" class="page-legal__headline">
                    {{ __('public.legal.privacy_title') }}
                </h1>
                <p class="page-legal__lead">
                    {{ __('public.legal.privacy_lead', ['app' => config('app.name')]) }}
                </p>
                <p class="page-legal__meta">{{ __('public.legal.privacy_updated') }}</p>
            </div>
        </section>

        <section class="page-legal__content">
            <div class="page-legal__dots page-legal__dots--flat" aria-hidden="true"></div>

            <div class="container page-legal__content-inner">
                <div class="page-legal__panel">
                    <div class="page-legal__body">
                        @include('public.legal.'.app()->getLocale().'.privacy')
                    </div>

                    <footer class="page-legal__footer">
                        <p class="page-legal__footer-label">{{ __('public.legal.see_also') }}</p>
                        <a href="{{ localized_route('public.terms') }}" class="page-legal__footer-link">
                            {{ __('public.legal.terms_title') }}
                        </a>
                    </footer>
                </div>
            </div>
        </section>
    </article>
@endsection
