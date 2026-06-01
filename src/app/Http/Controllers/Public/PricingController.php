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
        $seekerPlans = Plan::where('entitlement_type', Entitlement::TYPE_JOB_SEEKER_ACCESS)
            ->where('is_active', true)
            ->orderBy('amount')
            ->get();

        return view('public.pricing', compact('seekerPlans'));
    }
}
