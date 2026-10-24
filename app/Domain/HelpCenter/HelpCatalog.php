<?php

namespace App\Domain\HelpCenter;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JsonException;

/** Public, reviewed product content only. Never indexes tenant records. */
class HelpCatalog
{
    private ?array $loaded = null;

    public function categories(): array
    {
        return collect(config('help.categories', []))->map(fn (array $category): array => [
            ...$category,
            'href' => route('help.category', $category['slug']),
            'url' => route('help.category', $category['slug']),
            'article_count' => count(array_filter($this->articles(), fn ($article) => $article['category'] === $category['slug'])),
        ])->values()->all();
    }

    public function articles(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }
        $articles = [];
        foreach (glob(config('help.content_path').'/*.json') ?: [] as $path) {
            try {
                $article = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
            if (! is_array($article) || $this->errors($article, $path) !== []
                || $article['status'] !== 'published' || $article['updated_at'] > now()->toDateString()) {
                continue;
            }
            $article['order'] = (int) ($article['order'] ?? 100);
            $articles[$article['slug']] = $article;
        }
        uasort($articles, fn ($a, $b) => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);

        return $this->loaded = $articles;
    }

    public function find(string $slug): ?array
    {
        $article = $this->articles()[$slug] ?? null;
        if (! $article) {
            return null;
        }
        $article = array_intersect_key($article, array_flip(['slug', 'title', 'summary', 'category', 'updated_at', 'roles', 'order', 'sections', 'related', 'action', 'screenshots']));
        $article['href'] = route('help.article', $slug);
        $article['url'] = $article['href'];
        $article['sections'] = array_map(fn (array $section): array => [
            ...$section,
            'html' => Str::markdown($section['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ], $article['sections']);
        $article['related'] = collect($article['related'] ?? [])->filter(fn ($related) => isset($this->articles()[$related]))
            ->map(fn ($related) => $this->summary($this->articles()[$related]))->values()->all();
        $article['screenshots'] = $this->screenshots($article);
        $article['reading_minutes'] = max(1, (int) ceil(str_word_count(implode(' ', array_column($article['sections'], 'body'))) / 180));
        if (isset($article['action'])) {
            $article['action']['href'] = route('help.open', $article['action']['key']);
            $article['action']['url'] = $article['action']['href'];
        }

        return $article;
    }

    public function summary(array $article): array
    {
        return [
            ...array_intersect_key($article, array_flip(['slug', 'title', 'summary', 'category', 'updated_at', 'roles', 'order'])),
            'href' => route('help.article', $article['slug']),
            'url' => route('help.article', $article['slug']),
        ];
    }

    public function summaries(?string $category = null): array
    {
        return collect($this->articles())->filter(fn ($article) => $category === null || $article['category'] === $category)
            ->map(fn ($article) => $this->summary($article))->values()->all();
    }

    public function screenshots(array $article): array
    {
        $manifest = $this->manifest();

        return collect($article['screenshots'] ?? [])->map(function ($shot) use ($manifest) {
            $asset = $manifest[$shot['key']] ?? null;
            if (! $asset || ! $this->validMedia($asset)) {
                return null;
            }

            return [...$shot, ...array_intersect_key($asset, array_flip(['path', 'width', 'height', 'captured_at']))];
        })->filter()->values()->all();
    }

    public function manifest(): array
    {
        $path = config('help.media_manifest');
        if (! is_file($path)) {
            return [];
        }
        try {
            $manifest = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);

            return is_array($manifest) ? $manifest : [];
        } catch (JsonException) {
            return [];
        }
    }

    public function validMedia(mixed $asset): bool
    {
        if (! is_array($asset)) {
            return false;
        }
        $path = $asset['path'] ?? '';
        if (! is_string($path) || ! preg_match('#^/images/help/[a-z0-9][a-z0-9/_.-]*\.(?:png|jpe?g|webp)$#', $path)
            || str_contains($path, '..') || ! is_file(public_path(ltrim($path, '/')))
            || ! is_int($asset['width'] ?? null) || $asset['width'] <= 0
            || ! is_int($asset['height'] ?? null) || $asset['height'] <= 0) {
            return false;
        }
        $file = public_path(ltrim($path, '/'));
        $dimensions = @getimagesize($file);
        if (! $dimensions || $dimensions[0] !== $asset['width'] || $dimensions[1] !== $asset['height']) {
            return false;
        }
        if (isset($asset['sha256']) && (! is_string($asset['sha256']) || ! preg_match('/^[a-f0-9]{64}$/', $asset['sha256'])
            || ! hash_equals($asset['sha256'], hash_file('sha256', $file)))) {
            return false;
        }

        return true;
    }

    /** Publication validation; invalid files fail closed on public routes. */
    public function errors(array $article, string $path): array
    {
        $errors = [];
        foreach (['slug', 'title', 'summary', 'category', 'updated_at', 'status'] as $field) {
            if (! is_string($article[$field] ?? null) || trim($article[$field]) === '') {
                $errors[] = "Missing {$field}";
            }
        }
        $slug = is_string($article['slug'] ?? null) ? $article['slug'] : '';
        if (strlen($slug) > 120 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || basename($path, '.json') !== $slug) {
            $errors[] = 'Slug must match its file name';
        }
        if (! in_array($article['category'] ?? null, array_column(config('help.categories', []), 'slug'), true)) {
            $errors[] = 'Unknown category';
        }
        if (! in_array($article['status'] ?? null, ['draft', 'published'], true)) {
            $errors[] = 'Invalid status';
        }
        $date = is_string($article['updated_at'] ?? null) ? $article['updated_at'] : '';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            $errors[] = 'Invalid updated date';
        }
        foreach (['keywords', 'roles', 'related'] as $field) {
            if (! is_array($article[$field] ?? null) || ! array_is_list($article[$field]) || collect($article[$field])->contains(fn ($value) => ! is_string($value))) {
                $errors[] = "Invalid {$field}";
            }
        }
        $sections = $article['sections'] ?? [];
        if (! is_array($sections) || ! array_is_list($sections) || $sections === []) {
            $errors[] = 'At least one section is required';
            $sections = [];
        }
        $ids = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                $errors[] = 'Invalid article section';

                continue;
            }
            $id = is_string($section['id'] ?? null) ? $section['id'] : '';
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) || in_array($id, $ids, true)
                || ! is_string($section['title'] ?? null) || trim($section['title']) === '' || ! is_string($section['body'] ?? null) || trim($section['body'] ?? '') === '') {
                $errors[] = 'Invalid or duplicate article section';
            }
            $ids[] = $id;
        }
        if (isset($article['action']) && (! is_array($article['action']) || ! is_string($article['action']['key'] ?? null) || ! isset(config('help.actions')[$article['action']['key']]) || ! is_string($article['action']['label'] ?? null) || trim($article['action']['label']) === '')) {
            $errors[] = 'Unknown article action';
        }
        $shots = $article['screenshots'] ?? [];
        if (! is_array($shots) || ! array_is_list($shots)) {
            $errors[] = 'Invalid screenshot list';
            $shots = [];
        }
        foreach ($shots as $shot) {
            if (! is_array($shot) || ! is_string($shot['key'] ?? null) || ! is_string($shot['alt'] ?? null) || trim($shot['alt']) === '' || ! is_string($shot['caption'] ?? null)
                || ! in_array($shot['section'] ?? null, $ids, true)) {
                $errors[] = 'Invalid screenshot reference';
            }
        }

        return $errors;
    }

    /** CI/operator gate including relationships, routes, local links and media. */
    public function validate(): array
    {
        $errors = [];
        foreach (glob(config('help.content_path').'/*.json') ?: [] as $path) {
            try {
                $article = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $errors[] = basename($path).': Invalid JSON';

                continue;
            }
            if (! is_array($article)) {
                $errors[] = basename($path).': Expected an article object';

                continue;
            }
            $schemaErrors = $this->errors($article, $path);
            foreach ($schemaErrors as $error) {
                $errors[] = basename($path).': '.$error;
            }
            if ($schemaErrors !== []) {
                continue;
            }
            if (($article['status'] ?? '') !== 'published') {
                continue;
            }
            foreach ($article['related'] ?? [] as $slug) {
                if (! isset($this->articles()[$slug])) {
                    $errors[] = basename($path).': Missing published related article '.$slug;
                }
            }
            foreach ($article['screenshots'] ?? [] as $shot) {
                $asset = $this->manifest()[$shot['key']] ?? [];
                if (! $this->validMedia($asset)) {
                    $errors[] = basename($path).': Missing or invalid screenshot '.$shot['key'];
                }
            }
            foreach ($article['sections'] ?? [] as $section) {
                preg_match_all('#\]\(/help/articles/([a-z0-9-]+)(?:\#[a-z0-9-]+)?\)#', $section['body'], $links);
                foreach ($links[1] as $slug) {
                    if (! isset($this->articles()[$slug])) {
                        $errors[] = basename($path).': Broken article link '.$slug;
                    }
                }
            }
        }
        foreach ($this->manifest() as $key => $asset) {
            if (! is_string($key) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) || ! $this->validMedia($asset)) {
                $errors[] = 'Media manifest: missing or invalid asset '.$key;
            }
        }
        foreach (config('help.actions', []) as $key => $action) {
            if (! Route::has($action['route'])) {
                $errors[] = 'Broken application route for '.$key;
            }
        }

        if ($this->articles() === []) {
            $errors[] = 'No published Help Center articles are available';
        }

        return $errors;
    }
}
