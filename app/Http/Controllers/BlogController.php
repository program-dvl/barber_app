<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Services\SafeArticleRenderer;
use App\Models\Article;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        $articles = Article::with('user')
            ->publishable()->latest('published_at')->paginate(12)
            ->through(fn (Article $article): array => $this->summary($article));

        return Inertia::render('Blog', [
            'articles' => $articles,
            'seo' => [
                'title' => 'Service business operations blog | ClipperDesk',
                'description' => 'Reviewed articles about booking, scheduling, client service and day-to-day operations for appointment-led service businesses.',
                'canonical' => route('blog.index'),
                'image' => url('/images/marketing/editorial/resources-planning.webp'),
            ],
        ]);
    }

    public function article(Article $article, SafeArticleRenderer $renderer): Response
    {
        abort_unless(Article::query()->publishable()->whereKey($article)->exists(), 404);
        $article->load('user');
        $related = Article::query()->publishable()->where('topic', $article->topic)->whereKeyNot($article->getKey())
            ->with('user')->latest('published_at')->limit(2)->get()->map(fn (Article $item): array => $this->summary($item));
        $seoTitle = $article->seo_title ?? $article->title;
        if (! Str::contains(Str::lower($seoTitle), 'clipperdesk')) {
            $seoTitle .= ' | ClipperDesk';
        }

        return Inertia::render('Article', [
            'article' => [...$this->summary($article), 'html' => $renderer->render($article->content),
                'materially_updated_at' => $article->materially_updated_at?->toIso8601String(), 'content_owner' => $article->content_owner],
            'related' => $related,
            'seo' => [
                'title' => $seoTitle,
                'description' => $article->seo_description ?? Str::limit($article->content, 160),
                'canonical' => route('blog.article', ['article' => $article]),
                'image' => url('/images/marketing/editorial/resources-planning.webp'),
            ],
        ]);
    }

    private function summary(Article $article): array
    {
        return ['id' => $article->getKey(), 'slug' => $article->slug, 'title' => $article->title,
            'excerpt' => $article->excerpt, 'topic' => $article->topic, 'thumbnail' => $article->icon,
            'author' => $article->user?->name, 'published_at' => $article->published_at?->toIso8601String(),
            'seo_title' => $article->seo_title, 'seo_description' => $article->seo_description];
    }
}
