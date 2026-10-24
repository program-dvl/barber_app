<!DOCTYPE html>
<html lang="en-IN" data-theme="clipperdesk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="application-name" content="{{ config('brand.product_name') }}">
    <link rel="icon" href="{{ config('brand.favicon') }}" type="image/svg+xml">
    @inertiaHead
    @unless($__inertiaSsrResponse)
        <title data-inertia="">{{ $page['props']['seo']['title'] }}</title>
        @include('seo.metatags', ['seo' => $page['props']['seo']])
        @if(!empty($page['props']['structuredData']))
            <script type="application/ld+json" data-inertia="structured-data">{!! json_encode($page['props']['structuredData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif
    @endunless
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
</head>
<body class="antialiased">
    @if($__inertiaSsrResponse)
        {!! $__inertiaSsrResponse->body !!}
    @else
        @if(config('inertia.use_script_element_for_initial_page'))
            <script data-page="app" type="application/json">{!! json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            <div id="app">
        @else
            <div id="app" data-page="{{ json_encode($page) }}">
        @endif
            <div class="cd-marketing min-h-screen bg-[var(--surface-canvas)] text-[var(--text-default)]">
                <a class="cd-skip-link" href="#main-content">Skip to main content</a>
                @include('legal.marketing-header')
                <main id="main-content" tabindex="-1" class="help-center">
                    @include('help.content', ['help' => $page['props'], 'component' => $helpComponent])
                </main>
                @include('legal.marketing-footer')
            </div>
        </div>
    @endif
</body>
</html>
