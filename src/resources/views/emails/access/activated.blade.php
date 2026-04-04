<p>Hello {{ $entitlement->user->name }},</p>

<p>Your access has been activated for:</p>

<p><strong>{{ str_replace('_', ' ', $entitlement->type) }}</strong></p>

@if($entitlement->expires_at)
<p>Expiry date: {{ $entitlement->expires_at->format('M d, Y') }}</p>
@endif

<p>You can now log in and use the platform.</p>

<p>Regards,<br>{{ config('app.name') }}</p>