<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Employer;
use App\Models\JobSeeker;
use App\Models\PolicyDocument;
use App\Models\Program;
use App\Models\User;
use App\Services\Privacy\PolicyRegistryService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

final class RegisteredUserController extends Controller
{
    public function __construct(private readonly PolicyRegistryService $policies) {}

    public function create(Request $request): View
    {
        $programs = Program::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $selectedProgram = $programs->firstWhere('slug', trim((string) $request->query('program')));

        $policyDocuments = collect(PolicyDocument::TYPES)
            ->mapWithKeys(fn (string $type): array => [$type => PolicyDocument::current($type)]);

        return view('auth.register', compact('programs', 'selectedProgram', 'policyDocuments'));
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

        if (config('privacy.registration.evidence_enabled')) {
            foreach (PolicyDocument::TYPES as $policyType) {
                $current = PolicyDocument::current($policyType);

                if ($current) {
                    $request->validate([
                        "policy_documents.{$policyType}" => ['required', 'integer'],
                        "policy_acknowledgements.{$policyType}" => ['required', 'accepted'],
                    ]);
                }
            }
        }

        $user = DB::transaction(function () use ($validated, $program, $request): User {
            $submittedPolicies = (array) $request->input('policy_documents', []);
            $lockedPolicies = $this->policies->lockRegistrationPolicies($submittedPolicies);
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
                Employer::create(['user_id' => $user->id]);
            }

            $this->policies->recordRegistrationAcknowledgements($user, $submittedPolicies, $lockedPolicies);

            return $user;
        });

        event(new Registered($user));

        Mail::to($user)->send(new WelcomeMail($user, $validated['role']));

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
