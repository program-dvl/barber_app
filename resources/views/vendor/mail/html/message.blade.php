@props(['stream' => 'account', 'subject' => null, 'logoSrc' => null, 'purpose' => null])
@php
    $streamLabel = match ($stream) {
        'security' => 'Security',
        'billing' => 'Billing',
        'marketplace' => 'Marketplace',
        default => 'Account',
    };
@endphp
<x-mail::layout :stream="$stream">
<x-slot:header>
<x-mail::header :url="config('brand.website_url')" :stream="$stream" :subject="$subject" :logo-src="$logoSrc">
{{ $stream === 'marketplace' ? config('brand.marketplace_product_name') : config('brand.product_name') }}
</x-mail::header>
</x-slot:header>

@if($stream !== 'marketplace')
<table class="message-kicker-table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<span class="message-kicker message-kicker-{{ $stream }}">{{ $streamLabel }} · {{ $stream === 'marketplace' ? config('brand.marketplace_product_name') : config('brand.product_name') }}</span>
</td>
</tr>
</table>
@endif

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer :stream="$stream" :purpose="$purpose">
{{ $stream === 'marketplace' ? config('brand.marketplace_product_name') : config('brand.product_name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
