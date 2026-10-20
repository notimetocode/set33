@extends('public.layouts.app')

@section('title', __('public.discover_docs.meta_title', ['app' => config('app.name')]))
@section('meta_description', __('public.discover_docs.meta_description'))

@section('content')
    <article class="page-legal page-discover-docs">
        <section class="page-legal__hero" aria-labelledby="discover-docs-heading">
            <div class="page-legal__dots" aria-hidden="true"></div>

            <div class="container page-legal__hero-inner">
                <p class="page-legal__eyebrow">{{ __('public.discover_docs.eyebrow') }}</p>
                <h1 id="discover-docs-heading" class="page-legal__headline">
                    {{ __('public.discover_docs.title') }}
                </h1>
                <p class="page-legal__lead">
                    {{ __('public.discover_docs.lead') }}
                </p>
                <p class="page-discover-docs__markdown">
                    <a href="{{ $markdownUrl }}">{{ __('public.discover_docs.markdown_link') }}</a>
                </p>
            </div>
        </section>

        <section class="page-legal__content">
            <div class="page-legal__dots page-legal__dots--flat" aria-hidden="true"></div>

            <div class="container page-legal__content-inner">
                <div class="page-legal__panel">
                    <div class="page-legal__body">
                        <h2>{{ __('public.discover_docs.endpoint_heading') }}</h2>
                        <p>{{ __('public.discover_docs.endpoint_body', ['endpoint' => $endpoint]) }}</p>

                        <h2>{{ __('public.discover_docs.rate_heading') }}</h2>
                        <p>{{ __('public.discover_docs.rate_body') }}</p>

                        <h2>{{ __('public.discover_docs.request_heading') }}</h2>
                        <p>{{ __('public.discover_docs.request_body') }}</p>

                        <h2>{{ __('public.discover_docs.curl_heading') }}</h2>
                        <h3>{{ __('public.discover_docs.curl_post_label') }}</h3>
                        <pre><code>{{ __('public.discover_docs.curl', ['endpoint' => $endpoint]) }}</code></pre>
                        <h3>{{ __('public.discover_docs.curl_get_label') }}</h3>
                        <pre><code>{{ __('public.discover_docs.curl_get', ['endpoint' => $endpoint]) }}</code></pre>

                        <h2>{{ __('public.discover_docs.response_heading') }}</h2>
                        <p>{{ __('public.discover_docs.response_body') }}</p>
                        <pre><code>{{ __('public.discover_docs.response_sample') }}</code></pre>

                        <h2>{{ __('public.discover_docs.checks_heading') }}</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('public.discover_docs.checks_id') }}</th>
                                    <th scope="col">{{ __('public.discover_docs.checks_rule') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (\App\Services\Discover\DiscoverCheckCatalog::ids() as $id)
                                    <tr>
                                        <th scope="row"><code>{{ $id }}</code></th>
                                        <td>{{ __('public.discover_docs.checks.'.$id) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h2>{{ __('public.discover_docs.status_heading') }}</h2>
                        <p>{{ __('public.discover_docs.status_body') }}</p>
                    </div>
                </div>
            </div>
        </section>
    </article>
@endsection
