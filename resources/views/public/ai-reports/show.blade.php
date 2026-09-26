@extends('public.layouts.app')

@section('title', 'AI-отчёт — '.config('app.name'))
@section('meta_description', 'Общий AI-отчёт '.config('app.name'))

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <article class="page-shared-ai-report">
        <div class="container page-shared-ai-report__inner">
            <header class="page-shared-ai-report__header">
                <p class="page-shared-ai-report__eyebrow">AI-отчёт</p>
                <h1 class="page-shared-ai-report__title">
                    {{ $report->site?->name ?: 'Отчёт' }}
                </h1>
                <p class="page-shared-ai-report__meta">
                    Период:
                    {{ $report->period_from?->format('d.m.Y') }}
                    —
                    {{ $report->period_to?->format('d.m.Y') }}
                    @if ($report->created_at)
                        · сформирован {{ $report->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
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
