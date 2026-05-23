<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Kairox Exchange</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:40px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">
                    <tr>
                        <td style="background:#6f4cb2; padding:24px 40px; border-radius:12px 12px 0 0;">
                            <span style="color:#ffffff; font-size:20px; font-weight:700; letter-spacing:-0.02em;">Kairox Exchange</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff; padding:40px; border-radius:0 0 12px 12px; border:1px solid #e5e7eb; border-top:none;">

                            <h2 style="margin:0 0 20px; font-size:22px; font-weight:700; color:#111827;">Welcome, {{ $user->name }}</h2>

                            <p style="margin:0 0 16px; color:#1f2937; font-size:15px; line-height:1.7;">
                                Your account has been created on Kairox Exchange. You are registered as a
                                <strong>{{ $role === 'employer' ? 'Employer' : 'Job Seeker' }}</strong>.
                            </p>

                            @if($role === 'job_seeker')
                            <p style="margin:0 0 24px; color:#1f2937; font-size:15px; line-height:1.7;">
                                To get the most out of the platform, complete your profile, upload your resume and documents, then subscribe to unlock access to all available opportunities.
                            </p>

                            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-left:4px solid #6f4cb2; border-radius:6px; padding:16px 20px; margin:0 0 28px;">
                                <p style="margin:0 0 8px; font-size:13px; font-weight:600; color:#374151;">Getting started</p>
                                <p style="margin:0 0 6px; font-size:13px; color:#6b7280;">1. Complete your profile and upload your resume</p>
                                <p style="margin:0 0 6px; font-size:13px; color:#6b7280;">2. Subscribe to activate job seeker access</p>
                                <p style="margin:0; font-size:13px; color:#6b7280;">3. Browse and apply to published opportunities</p>
                            </div>
                            @else
                            <p style="margin:0 0 24px; color:#1f2937; font-size:15px; line-height:1.7;">
                                Complete your company profile, then subscribe to unlock posting access and start reaching qualified candidates on the platform.
                            </p>

                            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-left:4px solid #50b7a4; border-radius:6px; padding:16px 20px; margin:0 0 28px;">
                                <p style="margin:0 0 8px; font-size:13px; font-weight:600; color:#374151;">Getting started</p>
                                <p style="margin:0 0 6px; font-size:13px; color:#6b7280;">1. Complete your company profile and upload your logo</p>
                                <p style="margin:0 0 6px; font-size:13px; color:#6b7280;">2. Subscribe to activate employer posting access</p>
                                <p style="margin:0; font-size:13px; color:#6b7280;">3. Create your first job listing for review</p>
                            </div>
                            @endif

                            <a href="{{ url('/dashboard') }}"
                               style="display:inline-block; background:#6f4cb2; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; padding:12px 24px; border-radius:8px;">
                                Go to Dashboard
                            </a>

                            <p style="margin:32px 0 0; padding-top:24px; border-top:1px solid #e5e7eb; color:#1f2937; font-size:15px; line-height:1.7;">
                                Regards,<br>
                                <strong>The Kairox Team</strong>
                            </p>

                            <p style="margin:16px 0 0; font-size:12px; color:#9ca3af;">
                                This is an automated notification from Kairox Exchange. Please do not reply to this email.
                            </p>

                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
