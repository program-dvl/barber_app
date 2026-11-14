{{-- Shared public projection fallback, only when the real SSR body is unavailable. --}}
<main id="marketplace-main" class="market mp-container" data-marketplace-fallback>
    <nav aria-label="Marketplace"><a href="/marketplace">Find an appointment</a> · <a href="/marketplace/account">My bookings</a> · <a href="/">For businesses</a></nav>
    <h1>{{ $content['title'] }}</h1>
    <p>{{ $content['description'] }}</p>
    @if(!empty($content['breadcrumbs']))
        <nav aria-label="Breadcrumbs">@foreach($content['breadcrumbs'] as $link)<a href="{{ $link['url'] }}">{{ $link['name'] }}</a>@unless($loop->last) / @endunless @endforeach</nav>
    @endif
    @if(($content['kind'] ?? '') === 'profile')
        <h2>Locations & usual hours</h2>
        @foreach($content['locations'] as $location)
            <section><h3>{{ $location['name'] }}</h3><p>{{ $location['address'] }}</p><p>Times shown in {{ $location['time_zone'] }}. Choose a date to see available appointments.</p>
            <ul>@foreach($location['hours'] as $hour)<li>{{ ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$hour['day']] }}: {{ $hour['opens_at'] }}–{{ $hour['closes_at'] }}</li>@endforeach</ul></section>
        @endforeach
        <h2>Services & starting prices</h2>
        <p>Prices depend on the location and professional you choose. Your selection is checked before booking.</p>
        @foreach($content['services'] as $service)
            <article><h3><a href="{{ $service['url'] }}">{{ $service['name'] }}</a></h3><p>{{ $service['description'] }}</p><p>From {{ $service['currency'] }} {{ number_format($service['price_minor'] / 100, 2) }} · {{ $service['duration_minutes'] }} minutes</p></article>
        @endforeach
        @if($content['reviews']['count'])
            <section><h2>Reviews from completed visits</h2><p>{{ $content['reviews']['average'] }} out of 5 · {{ $content['reviews']['count'] }} reviews</p>
            @foreach($content['reviews']['items'] as $review)<article><p>{{ $review['author_initials'] }} · {{ $review['rating'] }} out of 5 · {{ $review['date'] }} · Verified visit</p><p>{{ $review['body'] }}</p></article>@endforeach</section>
        @endif
        <p>Choose services and an appointment time on this page when it finishes loading. Payments are collected directly by the business; its booking policy applies.</p>
    @else
        <form method="GET" action="/marketplace"><label>Service or business <input name="q" maxlength="100"></label><label>City or area <input name="location" maxlength="150"></label><button type="submit">Search appointments</button></form>
        @if(!empty($content['alternatives']))<section><h2>Explore outside your selected area</h2><p>No matches were found within 25 km. These alternatives are farther away; distances are straight-line estimates.</p>@foreach($content['alternatives'] as $card)<p><a href="{{ $card['url'] }}">{{ $card['name'] }}</a> · About {{ $card['distance'] }} km away</p>@endforeach</section>@endif
        @forelse($content['cards'] ?? [] as $card)
            <article><h2><a href="{{ $card['url'] }}">{{ $card['name'] }}</a></h2><p>{{ $card['address'] }}</p>
            @if(!empty($card['service']))<p>{{ $card['service'] }} · From {{ $card['currency'] }} {{ number_format($card['price_minor'] / 100, 2) }}</p>@endif
            @if($card['reviews']['count'])<p>{{ $card['reviews']['average'] }} out of 5 · {{ $card['reviews']['count'] }} verified visits</p>@endif
            @if(!empty($card['new_on_marketplace']))<p>New on Marketplace</p>@endif
            <a href="{{ $card['url'] }}">Explore & book</a></article>
        @empty
            @if(!empty($content['launch']))
            <section><h2>Your next good day starts here.</h2><p>We’re welcoming our first businesses. Explore services and guides while businesses join your area. Images are editorial inspiration, not listed businesses.</p>
            <h3>Explore services</h3>@foreach($content['editorial']['categories'] as $category)<p><a href="/marketplace/explore/{{ $category['slug'] }}">{{ $category['name'] }}</a></p>@endforeach
            <h3>A little know-how before you go</h3>@foreach($content['editorial']['guides'] as $guide)<p><a href="/marketplace/guides/{{ $guide['slug'] }}">{{ $guide['title'] }}</a></p><p>{{ $guide['description'] }}</p>@endforeach
            <p>Enable JavaScript to request an email when businesses join your area.</p></section>
            @else
            <section><h2>No appointments to show in this area yet</h2><p>Try another area or contact a business you know.</p><a href="/marketplace">Search another area</a></section>
            @endif
        @endforelse
    @endif
    @if(!empty($content['links']))<nav aria-label="Explore local appointments"><h2>Explore local appointments</h2>@foreach($content['links'] as $link)<p><a href="{{ $link['url'] }}">{{ $link['name'] }}</a></p>@endforeach</nav>@endif
    @if(!empty($content['pagination']['previous']) || !empty($content['pagination']['next']))<nav aria-label="Result pages">@if($content['pagination']['previous'])<a href="{{ $content['pagination']['previous'] }}">Previous</a>@endif <span>Page {{ $content['pagination']['page'] }}</span> @if($content['pagination']['next'])<a href="{{ $content['pagination']['next'] }}">Next</a>@endif</nav>@endif
</main>
