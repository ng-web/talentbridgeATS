<?php

namespace App\Http\Controllers\Locked;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\Plan;
use Illuminate\Contracts\View\View;

final class SeekerAccessController extends Controller
{
    public function __invoke(): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('entitlement_type', Entitlement::TYPE_JOB_SEEKER_ACCESS)
            ->orderBy('amount')
            ->get();

        return view('locked.seeker', [
            'plans' => $plans,
        ]);
    }
}