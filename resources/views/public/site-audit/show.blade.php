@extends('public.layouts.app')

@section('title', __('public.site_audit.page_title', ['app' => config('app.name')]))
@section('meta_description', __('public.site_audit.page_meta'))

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <article class="page-site-audit">
        <div
            id="site-audit-app"
            class="page-site-audit__mount"
            data-site-audit
        ></div>

        <script
            type="application/json"
            id="site-audit-bootstrap"
        >@json($bootstrap)</script>
    </article>
@endsection

@push('scripts')
    @vite(['resources/js/public/site-audit.js'])
@endpush
