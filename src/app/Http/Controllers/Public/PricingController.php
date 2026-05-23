<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\Plan;
use Illuminate\Contracts\View\View;

final class PricingController extends Controller
{
    public function __invoke(): View
    {
        $seekerPlan    = Plan::where('entitlement_type', Entitlement::TYPE_JOB_SEEKER_ACCESS)->where('is_active', true)->first();
        $employerPlan  = Plan::where('entitlement_type', Entitlement::TYPE_EMPLOYER_POSTING_ACCESS)->where('is_active', true)->first();

        return view('public.pricing', compact('seekerPlan', 'employerPlan'));
    }
}