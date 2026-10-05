<tr>
<td>
<table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<p class="footer-brand"><strong>{{ config('brand.product_name') }}</strong></p>
<p>{{ config('brand.tagline') }}</p>
<p class="footer-links">
<a href="{{ config('brand.website_url') }}">Visit ClipperDesk</a>
<span aria-hidden="true">&nbsp;·&nbsp;</span>
<a href="{{ rtrim(config('brand.website_url'), '/') }}/security">Security</a>
@if (config('brand.support_email'))
<span aria-hidden="true">&nbsp;·&nbsp;</span>
<a href="mailto:{{ config('brand.support_email') }}">Get help</a>
@endif
</p>
<p class="footer-service-note">This operational email was sent to keep your account or workspace up to date. It is not a marketing message.</p>
<p>© {{ date('Y') }} {{ config('brand.company_name') }}. All rights reserved.</p>
</td>
</tr>
</table>
</td>
</tr>
