@php
    $title = 'Good Hours';
    $description = 'One calm place to run bookings, clients, staff, checkout, and the day ahead.';
    $image = null;
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

<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $robots }}">
<!-- OG-->
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Good Hours">
<meta property="og:locale" content="en_IN">
@if($image)
    <meta property="og:image" content="{{ $image }}">
@endif

<!-- Twitter Card Tags -->
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
@if($image)
    <meta name="twitter:image" content="{{ $image }}">
@endif
<meta name="twitter:card" content="summary_large_image">
