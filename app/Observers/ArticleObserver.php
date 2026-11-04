<?php

namespace App\Observers;

use App\Models\Article;
use Illuminate\Support\Facades\Storage;

class ArticleObserver
{
    /**
     * Handle the Article "deleted" event.
     */
    public function deleted(Article $article): void
    {
        $path = $article->thumbnail;
        // Only article-managed media belongs to this lifecycle. Never remove
        // another disk's private file or an image still used by another article.
        if (! is_string($path) || ! str_starts_with($path, 'articles/')
            || str_contains($path, '\\') || array_intersect(explode('/', $path), ['.', '..'])) {
            return;
        }

        if (Article::query()->whereKeyNot($article->getKey())->where('thumbnail', $path)->exists()) {
            return;
        }

        Storage::disk(config('articles.disk', 'public'))->delete($path);
    }
}
