@extends('public.layouts.app')

@section('title', __('public.shared_report.show_title', ['app' => config('app.name')]))
@section('meta_description', __('public.shared_report.show_meta', ['app' => config('app.name')]))

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <article class="page-shared-ai-report">
        <div class="container page-shared-ai-report__inner">
            <header class="page-shared-ai-report__header">
                <p class="page-shared-ai-report__eyebrow">{{ __('public.shared_report.eyebrow') }}</p>
                <h1 class="page-shared-ai-report__title">
                    {{ $report->site?->name ?: __('public.shared_report.fallback_name') }}
                </h1>
                <p class="page-shared-ai-report__meta">
                    {{ __('public.shared_report.period') }}
                    {{ $report->period_from?->translatedFormat('d.m.Y') }}
                    —
                    {{ $report->period_to?->translatedFormat('d.m.Y') }}
                    @if ($report->created_at)
                        · {{ __('public.shared_report.generated', [
                            'datetime' => $report->created_at->timezone(config('app.timezone'))->translatedFormat('d.m.Y H:i'),
                        ]) }}
                    @endif
                </p>
            </header>

            <div
                id="shared-ai-report"
                class="page-shared-ai-report__body"
                data-shared-ai-report
            ></div>

            <script
                type="application/json"
                id="shared-ai-report-data"
            >@json($payload)</script>
        </div>
    </article>
@endsection

@push('scripts')
    @vite(['resources/js/public/shared-ai-report.js'])
@endpush
