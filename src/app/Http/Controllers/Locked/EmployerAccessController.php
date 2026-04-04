<?php

namespace App\Http\Controllers\Locked;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class EmployerAccessController extends Controller
{
    public function __invoke(): View
    {
        return view('locked.employer');
    }
}