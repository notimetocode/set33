@extends('public.layouts.app')

@section('title', __('public.shared_report.password_title', ['app' => config('app.name')]))
@section('meta_description', __('public.shared_report.password_meta'))

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <article class="page-shared-ai-report page-shared-ai-report--password">
        <div class="container page-shared-ai-report__inner page-shared-ai-report__inner--narrow">
            <header class="page-shared-ai-report__header">
                <p class="page-shared-ai-report__eyebrow">{{ __('public.shared_report.eyebrow') }}</p>
                <h1 class="page-shared-ai-report__title">{{ __('public.shared_report.password_heading') }}</h1>
                <p class="page-shared-ai-report__meta">
                    {{ __('public.shared_report.password_lead') }}
                </p>
            </header>

            <form
                method="post"
                action="{{ localized_route('public.ai-reports.unlock', ['token' => $token]) }}"
                class="page-shared-ai-report__form"
            >
                @csrf

                <div class="mb-3">
                    <label
                        class="form-label"
                        for="shared-ai-report-password"
                    >{{ __('public.shared_report.password_label') }}</label>
                    <input
                        id="shared-ai-report-password"
                        type="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        autocomplete="current-password"
                        required
                        autofocus
                    >
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    {{ __('public.shared_report.password_submit') }}
                </button>
            </form>
        </div>
    </article>
@endsection
