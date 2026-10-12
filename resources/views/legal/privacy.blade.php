<!DOCTYPE html>
<html lang="en" data-theme="clipperdesk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="application-name" content="Clipperdesk">
    @inertiaHead
    @unless($__inertiaSsrResponse)
        <title>{{ $seo['title'] }}</title>
    @endunless
    <link rel="icon" href="{{ config('brand.favicon') }}" type="image/svg+xml">
    @include('seo.metatags', ['seo' => $seo])
    @routes
    @vite(['resources/js/app.js', 'resources/js/Pages/PrivacyPolicy.vue'])
</head>
<body class="antialiased">
    @if($__inertiaSsrResponse)
        {!! $__inertiaSsrResponse->body !!}
    @else
        {{-- Render the full policy even when the optional Vue SSR daemon is unavailable.
             The normal Inertia client then mounts the existing shared marketing shell. --}}
        @if(config('inertia.use_script_element_for_initial_page'))
            <script data-page="app" type="application/json">{!! json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            <div id="app">
        @else
            <div id="app" data-page="{{ json_encode($page) }}">
        @endif
            <div class="cd-marketing min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]">
                <a class="cd-skip-link" href="#main-content">Skip to main content</a>
                @include('legal.marketing-header')
                <main id="main-content" tabindex="-1">{!! $content !!}</main>
                @include('legal.marketing-footer')
            </div>
        </div>
    @endif
</body>
</html>
