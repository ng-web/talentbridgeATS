<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Employer;
use App\Models\JobSeeker;
use App\Models\Program;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

final class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $programs = Program::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $selectedProgram = $programs->firstWhere('slug', trim((string) $request->query('program')));

        return view('auth.register', compact('programs', 'selectedProgram'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role' => ['required', 'in:job_seeker'],
            'program' => [
                'required',
                'string',
                Rule::exists('programs', 'slug')->where('is_active', true),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $program = Program::query()
            ->where('slug', $validated['program'])
            ->where('is_active', true)
            ->firstOrFail();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        if ($validated['role'] === 'job_seeker') {
            JobSeeker::create([
                'user_id' => $user->id,
                'program_id' => $program->id,
            ]);
        }

        if ($validated['role'] === 'employer') {
            Employer::create([
                'user_id' => $user->id,
            ]);
        }

        event(new Registered($user));

        Mail::to($user)->send(new WelcomeMail($user, $validated['role']));

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
