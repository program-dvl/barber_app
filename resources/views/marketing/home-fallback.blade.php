{{-- Essential acquisition content remains readable when SSR is unavailable. Vue replaces this on hydration. --}}
<main id="main-content" class="salon-home">
    <section class="salon-journey"><div class="cd-public-container">
        <a href="{{ route('marketing.home') }}">ClipperDesk Business</a>
        <h1>A good day. By design.</h1>
        <p>{{ $page['props']['homepage']['lead'] }}</p>
        <p>{{ $page['props']['homepage']['audience'] }}</p>
        <div class="salon-actions"><a class="cd-button cd-button-primary" href="{{ route('register') }}">Start your trial</a><a class="cd-button cd-button-secondary" href="{{ route('marketing.pricing') }}">See pricing</a></div>
        <h2>Stay in the moment. Keep the day together.</h2>
        @foreach($page['props']['homepage']['moment_benefits'] as $item)
            <article><h3>{{ $item['title'] }}</h3><p>{{ $item['body'] }}</p></article>
        @endforeach
        <h2>From first booking to next visit.</h2>
        <p>Manage appointments, welcome walk-ins and keep client history and team schedules together.</p>
        <ul><li><a href="{{ route('marketing.features.show', 'calendar-and-walk-ins') }}">Explore booking and walk-ins</a></li><li><a href="{{ route('marketing.features.show', 'client-management') }}">Remember your clients</a></li><li><a href="{{ route('marketing.features.show', 'checkout-and-reporting') }}">Record payments and review sales</a></li></ul>
        <p>Checkout records payments received outside ClipperDesk. It does not charge cards or send provider refunds. Online deposit collection is not currently available.</p>
        <h2>Made for people-powered businesses.</h2><p>{{ $page['props']['homepage']['audience'] }}</p>
        <a href="{{ route('marketing.solutions') }}">Explore all 13 business types</a>
        <div class="salon-business-links">
            @foreach($page['props']['homepage']['businesses'] as $business)
                <a href="{{ route('marketing.solutions.show', $business['slug']) }}">{{ $business['label'] }}</a>
            @endforeach
        </div>
        <h2>Built around your working day.</h2>
        @foreach($page['props']['homepage']['use_cases'] as $item)
            <article><h3>{{ $item['title'] }}</h3><p>{{ $item['body'] }}</p><a href="{{ route($item['route'], $item['slug']) }}">{{ $item['label'] }}</a></article>
        @endforeach
        <h2>Straight answers.</h2>
        @foreach($page['props']['homepage']['faqs'] as $faq)
            <details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>
        @endforeach
        <h2>Start with your working day.</h2><p>Add services, your team and working hours. Preview your booking page before sharing it. CSV imports help bring existing client, staff, service and product records into your business.</p>
        <p>The interactive sample day needs JavaScript. You can still explore features, pricing and setup help.</p>
        <a href="{{ route('help.index') }}">Help Center</a>
    </div></section>
</main>
