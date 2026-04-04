<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class PricingController extends Controller
{
    public function __invoke(): View
    {
        return view('public.pricing');
    }
}