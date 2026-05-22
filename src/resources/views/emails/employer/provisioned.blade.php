<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Kairox Exchange account is ready</title>
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

                            <h2 style="margin:0 0 20px; font-size:22px; font-weight:700; color:#111827;">Your account is ready</h2>

                            <p style="margin:0 0 20px; color:#1f2937; font-size:15px; line-height:1.7;">
                                Hi {{ $user->name }},
                            </p>

                            <p style="margin:0 0 20px; color:#1f2937; font-size:15px; line-height:1.7;">
                                Your Kairox Exchange employer account has been set up. Use the login details below to access the platform for the first time. You will be prompted to create a new password upon logging in.
                            </p>

                            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-left:4px solid #6f4cb2; border-radius:6px; padding:16px 20px; margin:0 0 24px;">
                                <p style="margin:0 0 12px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.06em; font-weight:600;">Login Credentials</p>

                                <table cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td style="font-size:13px; color:#6b7280; padding-bottom:8px; width:160px;">Email Address</td>
                                        <td style="font-size:14px; color:#111827; font-weight:600; padding-bottom:8px;">{{ $user->email }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-size:13px; color:#6b7280;">Temporary Password</td>
                                        <td style="font-size:14px; color:#111827; font-weight:700; font-family:monospace; letter-spacing:0.04em;">{{ $temporaryPassword }}</td>
                                    </tr>
                                </table>
                            </div>

                            <p style="margin:0 0 28px; color:#1f2937; font-size:15px; line-height:1.7;">
                                For security, please log in and change your password immediately. If you did not expect this email or believe it was sent in error, please contact our team.
                            </p>

                            <a href="{{ $loginUrl }}"
                               style="display:inline-block; background:#6f4cb2; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; padding:12px 24px; border-radius:8px;">
                                Log In to Kairox Exchange
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
