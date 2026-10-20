<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Discover\DiscoverDocsMarkdown;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class DiscoverDocsPageController extends Controller
{
    public function __invoke(): View
    {
        return view('public.discover-docs.show', [
            'endpoint' => url('/api/public/discover-checks'),
            'markdownUrl' => localized_route('public.discover-docs.markdown'),
        ]);
    }

    public function markdown(DiscoverDocsMarkdown $markdown): Response
    {
        $body = $markdown->render(url('/api/public/discover-checks'));

        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }
}
