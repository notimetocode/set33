<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PrivacyPolicyController extends Controller
{
    public function __invoke(): View
    {
        return view('public.privacy');
    }
}
