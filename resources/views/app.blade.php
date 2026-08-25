<!DOCTYPE html>
<html lang="en-IN" data-theme="clipperdesk" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="application-name" content="{{ config('brand.product_name') }}">
        <meta name="theme-color" content="#0f172a">
        <link rel="icon" href="{{ config('brand.favicon') }}" type="image/svg+xml">

        @include('seo.metatags', ['seo' => $page['props']['seo'] ?? null])
        <!-- Scripts -->
        @routes

        {{-- This is JS for LemonSqueezy --}}
        {{-- @lemonJS--}}
        {{-- This is JS for Paddle --}}
        {{-- @paddleJS--}}

        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])

        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia

        @php($structuredData = app(\App\Support\Seo\StructuredDataGraph::class)->for(request(), $page['props'] ?? []))
        @if($structuredData)
            <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif
    </body>
</html>
