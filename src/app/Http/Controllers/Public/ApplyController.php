<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Contracts\View\View;

final class ApplyController extends Controller
{
    public function __invoke(): View
    {
        $programs = Program::query()
            ->orderBy('name')
            ->get();

        return view('public.apply', compact('programs'));
    }
}