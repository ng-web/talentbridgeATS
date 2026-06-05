<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:sans-serif;background:#f4f4f6;margin:0;padding:24px;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e5e7eb;">

    <h2 style="margin:0 0 4px;font-size:20px;color:#111827;">New Payment Assistance Request</h2>
    <p style="margin:0 0 24px;color:#6b7280;font-size:14px;">A new request has been submitted through Kairox Exchange.</p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <tr>
            <td style="padding:8px 0;color:#6b7280;width:140px;">Applicant</td>
            <td style="padding:8px 0;color:#111827;font-weight:600;">{{ $assistanceRequest->full_name }}</td>
        </tr>
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:8px 0;color:#6b7280;">Email</td>
            <td style="padding:8px 0;color:#111827;">{{ $assistanceRequest->email }}</td>
        </tr>
        @if($assistanceRequest->phone)
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:8px 0;color:#6b7280;">Phone</td>
            <td style="padding:8px 0;color:#111827;">{{ $assistanceRequest->phone }}</td>
        </tr>
        @endif
        @if($assistanceRequest->whatsapp)
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:8px 0;color:#6b7280;">WhatsApp</td>
            <td style="padding:8px 0;color:#111827;">{{ $assistanceRequest->whatsapp }}</td>
        </tr>
        @endif
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:8px 0;color:#6b7280;">Programme</td>
            <td style="padding:8px 0;color:#111827;font-weight:600;">{{ $assistanceRequest->program_name }}</td>
        </tr>
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:8px 0;color:#6b7280;">Amount</td>
            <td style="padding:8px 0;color:#111827;font-weight:600;">{{ $assistanceRequest->currency }} {{ number_format((float)$assistanceRequest->amount, 0) }}</td>
        </tr>
        @if($assistanceRequest->message)
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:8px 0;color:#6b7280;vertical-align:top;">Message</td>
            <td style="padding:8px 0;color:#111827;">{{ $assistanceRequest->message }}</td>
        </tr>
        @endif
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:8px 0;color:#6b7280;">Submitted</td>
            <td style="padding:8px 0;color:#111827;">{{ $assistanceRequest->created_at->format('M d, Y \a\t g:ia') }}</td>
        </tr>
    </table>

    <div style="margin-top:24px;padding-top:24px;border-top:1px solid #e5e7eb;text-align:center;">
        <p style="font-size:12px;color:#9ca3af;margin:0;">Kairox Exchange — Admin Notification</p>
    </div>
</div>
</body>
</html>
