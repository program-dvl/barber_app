@if($useLogoImage ?? false)
    <img src="{{ config('brand.logo') }}" alt="Clipperdesk" width="422" height="106" class="block h-auto w-[17.5rem] max-w-full object-contain">
@else
<span class="inline-flex max-w-full flex-wrap items-center gap-2.5 {{ ($inverse ?? false) ? 'text-white' : 'text-[var(--text-strong)]' }}">
    <img src="{{ config(($inverse ?? false) ? 'brand.logo_mark_inverse' : 'brand.logo_mark') }}" alt="" width="64" height="64" class="h-[40px] w-auto shrink-0 object-contain" aria-hidden="true">
    <span class="min-w-0 whitespace-nowrap leading-tight"><span class="cd-display block text-xl font-bold leading-none tracking-[-0.04em]">Clipperdesk</span><span class="mt-1 block text-[0.6875rem] font-medium {{ ($inverse ?? false) ? 'text-white/70' : 'text-[var(--text-muted)]' }}">{{ config('brand.tagline') }}</span></span>
</span>
@endif
