@php
    $title = config('brand.product_name');
    $description = config('brand.description');
    $image = url(config('brand.social_image'));
    $canonical = request()->url();
    $robots = app(\App\Support\Seo\PublicIndexation::class)->directive(request());
@endphp

@if(isset($seo))
    @php
        $title = $seo['title'] ?? $title;
        $description = $seo['description'] ?? $description;
        $image = $seo['image'] ?? $image;
        $canonical = $seo['canonical'] ?? $canonical;
    @endphp
@endif

<meta name="description" content="{{ $description }}" data-inertia="description">
<link rel="canonical" href="{{ $canonical }}" data-inertia="canonical">
<meta name="robots" content="{{ $robots }}" data-inertia="robots">
<!-- OG-->
<meta property="og:title" content="{{ $title }}" data-inertia="og:title">
<meta property="og:description" content="{{ $description }}" data-inertia="og:description">
<meta property="og:url" content="{{ $canonical }}" data-inertia="og:url">
<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}" data-inertia="og:type">
<meta property="og:site_name" content="{{ $seo['site_name'] ?? config('brand.product_name') }}" data-inertia="og:site_name">
<meta property="og:locale" content="{{ $seo['locale'] ?? 'en_IN' }}" data-inertia="og:locale">
@if($image)
    <meta property="og:image" content="{{ $image }}" data-inertia="og:image">
@endif

<!-- Twitter Card Tags -->
<meta name="twitter:title" content="{{ $title }}" data-inertia="twitter:title">
<meta name="twitter:description" content="{{ $description }}" data-inertia="twitter:description">
@if($image)
    <meta name="twitter:image" content="{{ $image }}" data-inertia="twitter:image">
@endif
<meta name="twitter:card" content="summary_large_image" data-inertia="twitter:card">
