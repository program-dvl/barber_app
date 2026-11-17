<main class="mx-auto max-w-3xl space-y-6 px-6 py-12">
    <a class="underline" href="{{ route('marketing.resources') }}">Resources</a>
    <h1 class="text-4xl font-bold">{{ $guide['title'] }}</h1>
    <p>{{ $guide['intro'] }}</p>
    <p>{{ $guide['reviewed_by'] }} · {{ $guide['reviewed_at'] }}</p>
    @foreach($guide['sections'] as $section)
        <section><h2 class="text-xl font-bold">{{ $section['title'] }}</h2><p>{{ $section['body'] }}</p></section>
    @endforeach
    @isset($guide['answer'])
        <details><summary>Reveal the worked answer</summary><p>{{ $guide['answer'] }}</p></details>
    @endisset
    <section>
        <h2 class="text-xl font-bold">{{ $guide['worksheet']['title'] }}</h2>
        <p>{{ $guide['worksheet']['instruction'] }}</p>
        @foreach($guide['worksheet']['rows'] as $row)
            <p class="my-4">{{ $row['title'] }} · {{ $row['prompt'] }}</p>
        @endforeach
        <ul class="list-disc pl-6">@foreach($guide['checklist'] as $item)<li>{{ $item }}</li>@endforeach</ul>
    </section>
    <p>{{ $guide['limitation'] }}</p>
    <section><h2 class="text-xl font-bold">{{ $guide['sources_title'] }}</h2>
        @foreach($guide['sources'] as $source)
            <p class="my-3"><a class="underline" href="{{ $source['href'] }}">{{ $source['label'] }}</a> · {{ $source['detail'] }}</p>
        @endforeach
    </section>
    <a class="underline" href="{{ route('marketing.features.show', $guide['feature']) }}">{{ $guide['feature_label'] }}</a>
</main>
