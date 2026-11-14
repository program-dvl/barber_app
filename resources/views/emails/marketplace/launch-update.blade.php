@php($logoSrc = app(\App\Support\Mail\MarketplaceEmailBrand::class)->source($message ?? null))
<x-mail::message stream="marketplace" purpose="area-updates" :logo-src="$logoSrc" subject="Your Marketplace area updates">
@if($businessName)
# A new place to explore

{{ $businessName }} has joined Clipperdesk Marketplace in the area you requested: {{ $area }}.

Explore its services, prices and current booking choices. Availability and the business’s booking policy apply.

<x-mail::button :url="$actionUrl">Explore the business</x-mail::button>

This is your requested area-launch update. You won’t receive ongoing promotions from this signup.
@elseif($welcome)
# You’re on the list

Thanks for subscribing to Marketplace updates for {{ $area }}.

We’ll email when businesses join your area. You’re subscribed already—there’s nothing else to confirm.

<x-mail::button :url="$actionUrl">Explore Marketplace</x-mail::button>

This signup is for your area-launch update, with no ongoing promotions. Unsubscribe anytime using the link below.
@else
# Confirm your area updates

You asked for an email when businesses join Clipperdesk Marketplace in {{ $area }}.

We’ll send one email when businesses join your area. No ongoing promotions.

Open the link below, then choose **Confirm updates** on the page that opens. Your confirmation link is valid for 24 hours.

<x-mail::button :url="$actionUrl">Confirm my area updates</x-mail::button>

If you didn’t request this, ignore this email. You haven’t been subscribed.
@endif

The Clipperdesk Marketplace team

<x-slot:subcopy>
[Unsubscribe or cancel this request]({{ $unsubscribeUrl }}). [Privacy policy]({{ url('/privacy-policy') }}).
</x-slot:subcopy>
</x-mail::message>
