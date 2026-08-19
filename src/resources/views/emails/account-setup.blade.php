<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up your Kairox Exchange account</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:40px 16px;">
        <tr><td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">
                <tr><td style="background:#6f4cb2; padding:24px 40px; border-radius:12px 12px 0 0; color:#fff; font-size:20px; font-weight:700;">Kairox Exchange</td></tr>
                <tr><td style="background:#fff; padding:40px; border-radius:0 0 12px 12px; border:1px solid #e5e7eb; border-top:none;">
                    <h2 style="margin:0 0 20px; font-size:22px; color:#111827;">Set up your account</h2>
                    <p style="color:#1f2937; font-size:15px; line-height:1.7;">An account has been prepared for you. Use the secure button below to establish your password.</p>
                    <p style="color:#1f2937; font-size:15px; line-height:1.7;">The link is single-use and expires in {{ $expiresInMinutes }} minutes. If you did not expect it, do not use the link and contact the Kairox team.</p>
                    <a href="{{ $setupUrl }}" style="display:inline-block; margin-top:8px; background:#6f4cb2; color:#fff; font-size:14px; font-weight:600; text-decoration:none; padding:12px 24px; border-radius:8px;">Set up account</a>
                    <p style="margin:32px 0 0; padding-top:24px; border-top:1px solid #e5e7eb; color:#6b7280; font-size:12px;">This automated message does not contain a password. Do not forward the setup link.</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
