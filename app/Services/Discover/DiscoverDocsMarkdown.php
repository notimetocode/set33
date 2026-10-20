<?php

namespace App\Services\Discover;

class DiscoverDocsMarkdown
{
    public function render(string $endpoint): string
    {
        $checks = [
            '| '.__('public.discover_docs.checks_id').' | '.__('public.discover_docs.checks_rule').' |',
            '| --- | --- |',
        ];

        foreach (DiscoverCheckCatalog::ids() as $id) {
            $rule = str_replace('|', '\\|', (string) __('public.discover_docs.checks.'.$id));
            $checks[] = '| `'.$id.'` | '.$rule.' |';
        }

        $parts = [
            '# '.__('public.discover_docs.title'),
            '',
            __('public.discover_docs.lead'),
            '',
            '## '.__('public.discover_docs.endpoint_heading'),
            '',
            __('public.discover_docs.endpoint_body', ['endpoint' => $endpoint]),
            '',
            '## '.__('public.discover_docs.rate_heading'),
            '',
            __('public.discover_docs.rate_body'),
            '',
            '## '.__('public.discover_docs.request_heading'),
            '',
            __('public.discover_docs.request_body'),
            '',
            '## '.__('public.discover_docs.curl_heading'),
            '',
            '### '.__('public.discover_docs.curl_post_label'),
            '',
            '```',
            __('public.discover_docs.curl', ['endpoint' => $endpoint]),
            '```',
            '',
            '### '.__('public.discover_docs.curl_get_label'),
            '',
            '```',
            __('public.discover_docs.curl_get', ['endpoint' => $endpoint]),
            '```',
            '',
            '## '.__('public.discover_docs.response_heading'),
            '',
            __('public.discover_docs.response_body'),
            '',
            '```json',
            __('public.discover_docs.response_sample'),
            '```',
            '',
            '## '.__('public.discover_docs.checks_heading'),
            '',
            implode("\n", $checks),
            '',
            '## '.__('public.discover_docs.status_heading'),
            '',
            __('public.discover_docs.status_body'),
            '',
        ];

        return implode("\n", $parts);
    }
}
