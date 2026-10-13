<!DOCTYPE html>
<html lang="en-IN" data-theme="clipperdesk" class="scroll-smooth">
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
        @inertia

    </body>
</html>
