@php($logoSource = $logoSrc ?? app(\App\Support\Mail\MarketplaceEmailBrand::class)->source())
@if($logoSource)
<td class="brand-logo-cell" width="56" style="width:56px;vertical-align:middle;padding-right:12px;">
<a href="{{ $url }}" aria-label="Clipperdesk Marketplace home" style="text-decoration:none;">
<img src="{{ $logoSource }}" width="44" height="44" alt="" style="display:block;width:44px;height:44px;border:0;" />
</a>
</td>
@endif
<td class="brand-copy" style="vertical-align:middle;text-align:left;">
<a href="{{ $url }}" style="text-decoration:none;color:#202d29;">
<span class="brand-name" style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:25px;line-height:30px;font-weight:700;letter-spacing:-0.8px;color:#202d29;">{{ config('brand.marketplace_product_name') }}</span>
<span class="brand-tagline" style="display:block;margin-top:3px;font-family:Arial,Helvetica,sans-serif;font-size:9px;line-height:15px;font-weight:600;letter-spacing:3px;color:#526844;">MARKETPLACE</span>
</a>
</td>
