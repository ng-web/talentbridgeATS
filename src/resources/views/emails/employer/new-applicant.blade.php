<p>Hello {{ $application->job->employer->user->name ?? $application->job->employer->company_name }},</p>

<p>A new applicant has applied for <strong>{{ $application->job->title }}</strong>.</p>

<p>Applicant: {{ $application->jobSeeker->user->name }}</p>

<p>Please log in to your dashboard to review the application.</p>

<p>Regards,<br>{{ config('app.name') }}</p>