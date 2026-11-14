@props(['stream' => 'account', 'purpose' => null])
<tr>
<td>
<table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<p class="footer-brand"><strong>{{ $stream === 'marketplace' ? config('brand.marketplace_product_name') : config('brand.product_name') }}</strong></p>
<p>{{ $stream === 'marketplace' ? 'Marketplace · A little time for you.' : config('brand.tagline') }}</p>
<p class="footer-links">
<a href="{{ rtrim(config('brand.website_url'), '/').($stream === 'marketplace' ? '/marketplace' : '') }}">{{ $stream === 'marketplace' ? 'Visit Clipperdesk Marketplace' : 'Visit ClipperDesk' }}</a>
<span aria-hidden="true">&nbsp;·&nbsp;</span>
<a href="{{ rtrim(config('brand.website_url'), '/') }}/security">Security</a>
@if (config('brand.support_email'))
<span aria-hidden="true">&nbsp;·&nbsp;</span>
<a href="mailto:{{ config('brand.support_email') }}">Get help</a>
@endif
</p>
<p class="footer-service-note">{{ $purpose === 'area-updates' ? 'You received this email for your Marketplace area updates. You can unsubscribe using the link above.' : ($stream === 'marketplace' ? 'This email helps you access your Marketplace account. Keep your sign-in link private.' : 'This operational email was sent to keep your account or workspace up to date. It is not a marketing message.') }}</p>
<p>© {{ date('Y') }} {{ $stream === 'marketplace' ? config('brand.marketplace_product_name') : config('brand.company_name') }}. All rights reserved.</p>
</td>
</tr>
</table>
</td>
</tr>
