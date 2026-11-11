@php($logoSrc = $toBusiness ? null : app(\App\Support\Mail\MarketplaceEmailBrand::class)->source($message ?? null))
<x-mail::message :stream="$toBusiness ? 'account' : 'marketplace'" :logo-src="$logoSrc">
# {{ $toBusiness ? 'New customer message' : 'New message from '.$businessName }}

{{ $toBusiness ? 'A customer sent a message about a booking at '.$businessName.'.' : 'You have a new message about your appointment.' }}

<x-mail::button :url="$conversationUrl">Open conversation</x-mail::button>

Read and reply in Messages. Please don’t reply to this email.
</x-mail::message>
