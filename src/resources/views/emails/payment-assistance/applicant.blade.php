<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:sans-serif;background:#f4f4f6;margin:0;padding:24px;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e5e7eb;">

    <h2 style="margin:0 0 8px;font-size:20px;color:#111827;">We received your request</h2>
    <p style="margin:0 0 24px;color:#6b7280;font-size:15px;">
        Hi {{ $assistanceRequest->full_name }}, thank you for your interest in the <strong>{{ $assistanceRequest->program_name }}</strong> programme.
    </p>

    <div style="background:#efe8fb;border-radius:12px;padding:20px;margin-bottom:24px;">
        <p style="margin:0 0 4px;font-size:13px;color:#6f4cb2;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Programme</p>
        <p style="margin:0 0 12px;font-size:16px;font-weight:700;color:#111827;">{{ $assistanceRequest->program_name }}</p>
        <p style="margin:0 0 4px;font-size:13px;color:#6f4cb2;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Programme Fee</p>
        <p style="margin:0;font-size:24px;font-weight:700;color:#6f4cb2;">{{ $assistanceRequest->currency }} {{ number_format((float)$assistanceRequest->amount, 0) }}</p>
    </div>

    <p style="color:#374151;font-size:15px;line-height:1.6;">
        A Kairox Exchange representative will contact you within <strong>1 business day</strong> to discuss payment arrangements and next steps.
    </p>

    <p style="color:#374151;font-size:15px;line-height:1.6;margin-top:12px;">
        If you have any questions in the meantime, reply to this email or contact us at
        <a href="mailto:{{ config('mail.admin_address', config('mail.from.address')) }}" style="color:#6f4cb2;">{{ config('mail.admin_address', config('mail.from.address')) }}</a>.
    </p>

    <div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;text-align:center;">
        <p style="font-size:12px;color:#9ca3af;margin:0;">Kairox Exchange &mdash; International Work & Study Programmes</p>
    </div>
</div>
</body>
</html>
