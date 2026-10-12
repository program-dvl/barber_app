{{-- No-JavaScript equivalent of MarketingHeader; hydrated visits reuse that component. --}}
@php($navigation = [['Product', 'marketing.features'], ['Industries', 'marketing.solutions'], ['Use cases', 'marketing.use-cases'], ['Pricing', 'marketing.pricing'], ['Resources', 'marketing.resources']])
<header class="cd-marketing-header sticky top-0 z-40 backdrop-blur">
    <div class="cd-public-container py-2.5">
        <div class="cd-header-shell flex min-h-16 items-center justify-between gap-4 px-3 sm:px-4">
            <a href="{{ route('marketing.home') }}" class="inline-flex min-h-11 items-center rounded-lg" aria-label="Clipperdesk home">@include('legal.product-mark')</a>
            <nav aria-label="Primary" class="cd-header-nav hidden items-center gap-0.5 lg:flex">
                @foreach($navigation as [$label, $routeName])<a href="{{ route($routeName) }}" class="cd-nav-link">{{ $label }}</a>@endforeach
            </nav>
            <div class="hidden items-center gap-2 lg:flex">
                @guest<a href="{{ route('login') }}" class="cd-button cd-button-quiet">Log in</a>@endguest
                <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="cd-button cd-button-primary cd-button-compact">{{ auth()->check() ? 'Open dashboard' : 'Start your trial' }}</a>
            </div>
            <details class="cd-privacy-native-menu lg:hidden">
                <summary class="cd-icon-button" aria-label="Open navigation"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></summary>
                <nav aria-label="Mobile primary" class="cd-mobile-menu-card">
                    @foreach($navigation as [$label, $routeName])<a href="{{ route($routeName) }}" class="cd-mobile-nav-link">{{ $label }}</a>@endforeach
                    <a href="{{ route('marketing.company') }}" class="cd-mobile-nav-link">Company</a>
                    <a href="{{ route('marketing.security') }}" class="cd-mobile-nav-link">Security</a>
                    @guest<a href="{{ route('login') }}" class="cd-button cd-button-secondary">Log in</a>@endguest
                    <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="cd-button cd-button-primary">{{ auth()->check() ? 'Open dashboard' : 'Start your trial' }}</a>
                </nav>
            </details>
        </div>
    </div>
</header>
