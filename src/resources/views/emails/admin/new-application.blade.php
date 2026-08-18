<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:sans-serif;background:#f4f4f6;margin:0;padding:24px;">
@php
    $seeker = $application->jobSeeker;
    $applicant = $seeker?->user;
    $job = $application->job;
@endphp
<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e5e7eb;">
    <h2 style="margin:0 0 4px;font-size:20px;color:#111827;">New Application Received</h2>
    <p style="margin:0 0 24px;color:#6b7280;font-size:14px;">A new job application was submitted through Kairox Exchange.</p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <tr><td style="padding:8px 0;color:#6b7280;width:160px;">Applicant</td><td style="padding:8px 0;color:#111827;font-weight:600;">{{ $applicant?->name ?: 'Not available' }}</td></tr>
        <tr style="border-top:1px solid #f3f4f6;"><td style="padding:8px 0;color:#6b7280;">Current Program</td><td style="padding:8px 0;color:#111827;">{{ $seeker?->program?->name ?: 'Not selected' }}</td></tr>
        <tr style="border-top:1px solid #f3f4f6;"><td style="padding:8px 0;color:#6b7280;">Job</td><td style="padding:8px 0;color:#111827;font-weight:600;">{{ $job?->title ?: 'Not available' }}</td></tr>
        <tr style="border-top:1px solid #f3f4f6;"><td style="padding:8px 0;color:#6b7280;">Submitted</td><td style="padding:8px 0;color:#111827;">{{ $application->applied_at?->format('M d, Y \a\t g:i A T') ?: $application->created_at?->format('M d, Y \a\t g:i A T') }}</td></tr>
    </table>

    @if($applicant)
        <p style="margin:28px 0 0;">
            <a href="{{ route('admin.users.show', $applicant) }}" style="display:inline-block;background:#6f4cb2;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;font-size:14px;">View Applicant in Admin</a>
        </p>
    @endif

    <p style="margin:28px 0 0;padding-top:20px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">Kairox Exchange — Operations Notification</p>
</div>
</body>
</html>
