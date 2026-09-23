<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SpaController extends Controller
{
    public function __invoke(): View
    {
        return view('app.spa');
    }
}
