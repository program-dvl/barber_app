<!DOCTYPE html>
<html lang="en-IN" data-theme="good-hours" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
