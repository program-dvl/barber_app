@props(['url', 'stream' => 'account', 'subject' => null])
@php
    $streamLabel = match ($stream) {
        'security' => 'Security notice',
        'billing' => 'Billing update',
        default => 'Account update',
    };
@endphp
<tr>
<td class="header">
@if ($subject)
<div class="preheader">{{ $subject }}</div>
@endif
<table class="header-shell" align="center" width="640" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="brand-logo-cell" width="52">
<a href="{{ $url }}" class="brand-mark-link" aria-label="{{ config('brand.product_name') }} home">
<img class="brand-mark" src="{{ config('brand.email_logo_url') }}" width="44" height="44" alt="" />
</a>
</td>
<td class="brand-copy">
<a href="{{ $url }}" class="brand-link">
<span class="brand-name">{{ config('brand.product_name') }}</span>
<span class="brand-tagline">{{ config('brand.tagline') }}</span>
</a>
</td>
<td class="brand-context" align="right">
<span class="brand-context-label">{{ $streamLabel }}</span>
</td>
</tr>
</table>
</td>
</tr>
