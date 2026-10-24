<div class="help-container help-main">
    <nav aria-label="Breadcrumb"><a href="{{ route('help.index') }}">Help Center</a>@if($component === 'Help/Article') / <a href="{{ $help['category']['href'] }}">{{ $help['category']['title'] }}</a>@endif</nav>
    @if($component === 'Help/Article')
        <article style="max-width:760px;margin:28px auto">
            <h1>{{ $help['article']['title'] }}</h1>
            <p>{{ $help['article']['summary'] }}</p>
            <p>Updated <time datetime="{{ $help['article']['updated_at'] }}">{{ $help['article']['updated_at'] }}</time> · {{ $help['article']['reading_minutes'] }} min read</p>
            <nav aria-label="In this article"><ul>@foreach($help['article']['sections'] as $section)<li><a href="#{{ $section['id'] }}">{{ $section['title'] }}</a></li>@endforeach</ul></nav>
            @foreach($help['article']['sections'] as $section)
                <section id="{{ $section['id'] }}" class="help-article-section"><h2>{{ $section['title'] }}</h2><div class="help-prose">{!! $section['html'] !!}</div>
                    @foreach($help['article']['screenshots'] as $shot)
                        @if($shot['section'] === $section['id'])
                            <figure><a href="{{ $shot['path'] }}"><img src="{{ $shot['path'] }}" width="{{ $shot['width'] }}" height="{{ $shot['height'] }}" alt="{{ $shot['alt'] }}" loading="lazy" style="max-width:100%;height:auto"></a><figcaption>{{ $shot['caption'] }}</figcaption></figure>
                        @endif
                    @endforeach
                </section>
            @endforeach
            @isset($help['article']['action'])<p><a href="{{ $help['article']['action']['href'] }}">{{ $help['article']['action']['label'] }}</a></p>@endisset
            @if(count($help['related']))<aside><h2>Related articles</h2><ul>@foreach($help['related'] as $item)<li><a href="{{ $item['href'] }}">{{ $item['title'] }}</a></li>@endforeach</ul></aside>@endif
        </article>
    @elseif($component === 'Help/Category')
        <h1>{{ $help['category']['title'] }}</h1><p>{{ $help['category']['description'] }}</p>
        <ul>@foreach($help['articles'] as $item)<li><h2><a href="{{ $item['href'] }}">{{ $item['title'] }}</a></h2><p>{{ $item['summary'] }}</p></li>@endforeach</ul>
    @else
        <h1>How can we help?</h1><p>Find clear steps for setup, daily work and everything in between.</p>
        <form action="{{ route('help.index') }}" method="get" role="search"><label for="help-search">Search the Help Center</label><input id="help-search" name="q" type="search" maxlength="180" value="{{ $help['query'] }}"><button type="submit">Search</button></form>
        @if($help['query'] !== '')
            <h2>Search results</h2>
            @if(count($help['results']))<ul>@foreach($help['results'] as $item)<li><h3><a href="{{ $item['href'] }}">{{ $item['title'] }}</a></h3><p>{{ $item['summary'] }}</p></li>@endforeach</ul>@else<p>No matching articles yet. Try a shorter phrase or browse a topic below.</p>@endif
        @else
            <h2>Start here</h2><ul>@foreach($help['featured'] as $item)<li><a href="{{ $item['href'] }}">{{ $item['title'] }}</a></li>@endforeach</ul>
        @endif
        <h2>Browse by topic</h2><ul>@foreach($help['categories'] as $category)<li><h3><a href="{{ $category['href'] }}">{{ $category['title'] }}</a></h3><p>{{ $category['description'] }} {{ $category['article_count'] }} articles</p></li>@endforeach</ul>
    @endif
</div>
