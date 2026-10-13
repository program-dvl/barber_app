{{-- No-JavaScript equivalent of MarketingFooter; hydrated visits reuse that component. --}}
<footer class="cd-marketing-footer border-t border-white/10 bg-[#090d1a] text-white">
    <div class="cd-public-container py-14 sm:py-18">
        <div class="grid gap-12 lg:grid-cols-[1.35fr_2fr]">
            <div><a href="{{ route('marketing.home') }}" class="inline-flex min-h-11 items-center rounded-lg" aria-label="Clipperdesk home">@include('legal.product-mark', ['inverse' => true])</a><p class="mt-6 max-w-md text-sm leading-7 text-slate-400">Booking, client records, team schedules and checkout for appointment-led service businesses.</p></div>
            <div class="grid gap-9 sm:grid-cols-3">
                @foreach(['Product' => [['Features','marketing.features'],['Pricing','marketing.pricing'],['Security','marketing.security']], 'Explore' => [['Solutions','marketing.solutions'],['Use cases','marketing.use-cases'],['Resources','marketing.resources']], 'Company' => [['About Clipperdesk','marketing.company'],['Privacy policy','policy.show'],['Terms of service','terms.show'],['Refund policy','refund.show']]] as $label => $links)
                    <nav aria-label="{{ $label }} links"><h2 class="text-xs font-semibold text-slate-400">{{ $label }}</h2><ul class="mt-4 space-y-2">
                        @foreach($links as [$text, $routeName])<li><a href="{{ route($routeName) }}" class="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-slate-300 underline-offset-4 hover:text-white hover:underline">{{ $text }}</a></li>@endforeach
                    </ul></nav>
                @endforeach
            </div>
        </div>
        <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-xs leading-6 text-slate-400 sm:flex-row sm:items-center sm:justify-between"><p>© {{ now()->year }} Clipperdesk.</p></div>
    </div>
</footer>
