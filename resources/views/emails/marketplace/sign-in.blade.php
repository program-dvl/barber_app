@php($logoSrc = app(\App\Support\Mail\MarketplaceEmailBrand::class)->source($message ?? null))
<x-mail::message stream="marketplace" :logo-src="$logoSrc" subject="Your private Clipperdesk Marketplace sign-in link">
# Sign in to Clipperdesk

See your bookings, saved businesses and conversations.

<x-mail::button :url="$signInUrl">
Continue to my account
</x-mail::button>

This private link expires in 15 minutes and can be used once. If this is your first visit, we’ll help you create your customer account.

If you didn’t request this email, you can ignore it. Your account and bookings are unchanged.

Thanks,<br>
The Clipperdesk Marketplace team

<x-slot:subcopy>
If the button doesn’t open, copy this link into your browser:<br>
[{{ $signInUrl }}]({{ $signInUrl }})
</x-slot:subcopy>
</x-mail::message>
