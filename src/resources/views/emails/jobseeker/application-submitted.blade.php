<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your application has been submitted</title>
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

                            <h2 style="margin:0 0 20px; font-size:22px; font-weight:700; color:#111827;">Application submitted successfully</h2>

                            <p style="margin:0 0 20px; color:#1f2937; font-size:15px; line-height:1.7;">
                                Hi {{ $application->jobSeeker->user->name }},
                            </p>

                            <p style="margin:0 0 20px; color:#1f2937; font-size:15px; line-height:1.7;">
                                Your application has been received and forwarded to the employer for review. We will keep you informed as your application progresses through the selection process.
                            </p>

                            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-left:4px solid #6f4cb2; border-radius:6px; padding:16px 20px; margin:0 0 24px;">
                                <p style="margin:0 0 12px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.06em; font-weight:600;">Application Summary</p>

                                <table cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td style="font-size:13px; color:#6b7280; padding-bottom:6px; width:120px;">Position</td>
                                        <td style="font-size:14px; color:#111827; font-weight:600; padding-bottom:6px;">{{ $application->job->title }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-size:13px; color:#6b7280; padding-bottom:6px;">Company</td>
                                        <td style="font-size:14px; color:#111827; font-weight:600; padding-bottom:6px;">{{ $application->job->employer->company_name ?? $application->job->employer->user->name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-size:13px; color:#6b7280;">Date Applied</td>
                                        <td style="font-size:14px; color:#111827; font-weight:600;">{{ $application->applied_at?->format('M d, Y') }}</td>
                                    </tr>
                                </table>
                            </div>

                            <p style="margin:0 0 28px; color:#1f2937; font-size:15px; line-height:1.7;">
                                You can monitor the status of all your applications at any time from your dashboard. Status updates will be reflected there as the employer reviews your submission.
                            </p>

                            <a href="{{ route('jobseeker.applications.index') }}"
                               style="display:inline-block; background:#6f4cb2; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; padding:12px 24px; border-radius:8px;">
                                Track My Applications
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
