<p>Hello {{ $job->employer->user->name ?? $job->employer->company_name }},</p>

<p>Your job <strong>{{ $job->title }}</strong> has been approved and is now available to qualified seekers.</p>

<p>Please log in to your dashboard to manage applicants.</p>

<p>Regards,<br>{{ config('app.name') }}</p>