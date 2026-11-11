<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $page['props']['seo']['locale'] ?? 'en-IN') }}" data-theme="clipperdesk" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="application-name" content="{{ config('brand.product_name') }}">
        <meta name="theme-color" content="#0f172a">
        <link rel="icon" href="{{ config('brand.favicon') }}" type="image/svg+xml">

        @inertiaHead
        @unless($__inertiaSsrResponse)
            <title data-inertia="">{{ $page['props']['seo']['title'] ?? $page['props']['seoDefaults']['title'] ?? config('brand.product_name') }}</title>
            @include('seo.metatags', ['seo' => array_merge($page['props']['seoDefaults'] ?? [], $page['props']['seo'] ?? [])])
            @if(!empty($page['props']['structuredData']))
                <script type="application/ld+json" data-inertia="structured-data">{!! json_encode($page['props']['structuredData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @endif
        @endunless
        <!-- Scripts -->
        @routes

        {{-- This is JS for LemonSqueezy --}}
        {{-- @lemonJS--}}

        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])

    </head>
    <body class="antialiased">
        @if(!empty($page['props']['publicContent']) && !$__inertiaSsrResponse)
            @if(config('inertia.use_script_element_for_initial_page'))
                <script data-page="app" type="application/json">{!! json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                <div id="app">
            @else
                <div id="app" data-page="{{ json_encode($page) }}">
            @endif
                @include('marketplace.public-content', ['content' => $page['props']['publicContent']])
            </div>
        @else
            @inertia
        @endif

    </body>
</html>
