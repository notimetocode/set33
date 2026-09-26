@extends('public.layouts.app')

@section('title', 'Доступ к AI-отчёту — '.config('app.name'))
@section('meta_description', 'Введите пароль, чтобы открыть общий AI-отчёт')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <article class="page-shared-ai-report page-shared-ai-report--password">
        <div class="container page-shared-ai-report__inner page-shared-ai-report__inner--narrow">
            <header class="page-shared-ai-report__header">
                <p class="page-shared-ai-report__eyebrow">AI-отчёт</p>
                <h1 class="page-shared-ai-report__title">Отчёт защищён паролем</h1>
                <p class="page-shared-ai-report__meta">
                    Введите пароль, который вам передал автор отчёта.
                </p>
            </header>

            <form
                method="post"
                action="{{ route('public.ai-reports.unlock', ['token' => $token]) }}"
                class="page-shared-ai-report__form"
            >
                @csrf

                <div class="mb-3">
                    <label
                        class="form-label"
                        for="shared-ai-report-password"
                    >Пароль</label>
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
                    Открыть отчёт
                </button>
            </form>
        </div>
    </article>
@endsection
