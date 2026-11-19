{{-- Readable HTML when the optional Inertia renderer is unavailable. Vue replaces this on mount. --}}
<main id="main-content" class="mx-auto max-w-5xl px-6 py-12">
    <nav aria-label="Breadcrumb"><a href="{{ route('marketing.home') }}">Home</a> / <a href="{{ route('marketing.solutions') }}">Solutions</a></nav>
    @if(!empty($page['props']['solution']))
        @php($solution = $page['props']['solution'])
        <h1>{{ $solution['title'] }}</h1>
        <p>{{ $solution['description'] }}</p>
        <p>{{ $solution['fit'] }}</p>
        <p>{{ $page['props']['trialLabel'] }} free trial after email verification. No card required. No automatic charge.</p>
        <p><a href="{{ route('register') }}">Start your trial</a> · <a href="{{ route('marketing.product-tour') }}">See the product walkthrough</a> · <a href="{{ route('marketing.pricing') }}">Compare current pricing</a></p>
        <h2>{{ $solution['heading'] }}</h2>
        @foreach($solution['challenges'] as $item)<h3>{{ $item['title'] }}</h3><p>{{ $item['body'] }}</p>@endforeach
        <h2>{{ $solution['scenario']['title'] }}</h2><p>{{ $solution['scenario']['body'] }}</p>
        <ol>@foreach($solution['day'] as $step)<li>{{ $step }}</li>@endforeach</ol>
        @foreach($solution['screenshots'] as $screen)
            <figure><img src="{{ $screen['path'] }}" alt="{{ $screen['alt'] }}" width="{{ $screen['width'] }}" height="{{ $screen['height'] }}" loading="lazy" style="max-width:100%;height:auto"><figcaption>{{ $screen['caption'] }}</figcaption></figure>
        @endforeach
        <h2>Put your own working day to the test</h2><p>Use fictional clients first.</p>
        <ul>@foreach($solution['checks'] as $check)<li>{{ $check }}</li>@endforeach</ul>
        <h2>Know the limits before choosing</h2><ul>@foreach($solution['limits'] as $limit)<li>{{ $limit }}</li>@endforeach</ul>
        <h2>Answers before you start</h2><dl>@foreach($solution['faqs'] as $faq)<dt>{{ $faq['question'] }}</dt><dd>{{ $faq['answer'] }}</dd>@endforeach</dl>
        <h2>Look closer at the tools you would use</h2><ul>@foreach($page['props']['features'] as $feature)<li><a href="{{ route('marketing.features.show', $feature['slug']) }}">{{ $feature['label'] }}</a></li>@endforeach</ul>
        <h2>Setup and migration help</h2><p>Ask about transferring clients and upcoming bookings. Agree scope, timing and any cost before work starts. No client or patient records needed in your first email.</p>
    @else
        <h1>Find the workflow that fits your business</h1><p>Explore product workflows and limits before you switch. Specialist industry pages do not certify suitability.</p>
        @foreach(collect($page['props']['solutions'])->groupBy('group') as $group => $items)
            <h2>{{ $group }}</h2><ul>@foreach($items as $solution)<li><h3><a href="{{ route('marketing.solutions.show', $solution['slug']) }}">{{ $solution['label'] }}</a></h3><p>{{ $solution['description'] }}</p></li>@endforeach</ul>
        @endforeach
    @endif
    <p><a href="mailto:{{ config('brand.support_email') }}">Ask about your workflow</a> · <a href="{{ route('marketing.pricing') }}">View pricing</a></p>
</main>
