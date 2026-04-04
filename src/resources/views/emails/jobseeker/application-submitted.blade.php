<p>Hello {{ $application->jobSeeker->user->name }},</p>

<p>Your application for <strong>{{ $application->job->title }}</strong> has been submitted successfully.</p>

<p>You can log in to your dashboard to track the status of your application.</p>

<p>Regards,<br>{{ config('app.name') }}</p>