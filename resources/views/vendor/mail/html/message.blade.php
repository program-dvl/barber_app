@props(['stream' => 'account', 'subject' => null])
@php
    $streamLabel = match ($stream) {
        'security' => 'Security',
        'billing' => 'Billing',
        default => 'Account',
    };
@endphp
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('brand.website_url')" :stream="$stream" :subject="$subject">
{{ config('brand.product_name') }}
</x-mail::header>
</x-slot:header>

<table class="message-kicker-table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<span class="message-kicker message-kicker-{{ $stream }}">{{ $streamLabel }} · {{ config('brand.product_name') }}</span>
</td>
</tr>
</table>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
{{ config('brand.product_name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
