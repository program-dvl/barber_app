<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Services\SafeArticleRenderer;
use App\Models\Article;
use App\Support\Seo\PublicPage;
use App\Support\Seo\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(Request $request): Response
    {
        $requestedPage = $request->query->has('page') ? $request->query('page') : '1';
        abort_unless(is_string($requestedPage) && preg_match('/^[1-9][0-9]{0,8}$/D', $requestedPage), 404);
        $page = (int) $requestedPage;
        $query = Article::query()->publishable();
        $total = $query->count();
        abort_if($page > max(1, (int) ceil($total / 12)), 404);

        $indexUrl = PublicUrl::canonical(route('blog.index'));
        $articles = $query->with('user')->latest('published_at')->latest('id')
            ->paginate(12, page: $page, total: $total)->withPath($indexUrl)
            ->through(fn (Article $article): array => $this->summary($article));

        return PublicPage::render('Blog', [
            'articles' => [
                'data' => $articles->items(),
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'prev_page_url' => $page === 2 ? $indexUrl : $articles->previousPageUrl(),
                'next_page_url' => $articles->nextPageUrl(),
            ],
            'seo' => [
                'title' => $page > 1 ? 'Service business operations blog: page '.$page.' | ClipperDesk' : 'Service business operations blog | ClipperDesk',
                'description' => ($page > 1 ? 'Page '.$page.' of reviewed articles' : 'Reviewed articles').' about booking, scheduling, client service and day-to-day operations for appointment-led service businesses.',
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

        return PublicPage::render('Article', [
            'article' => [...$this->summary($article), 'html' => $renderer->render($article->content),
                'materially_updated_at' => $article->materially_updated_at?->toIso8601String()],
            'related' => $related,
            'seo' => [
                'title' => $seoTitle,
                'description' => $article->seo_description ?? Str::limit($article->content, 160),
                'canonical' => route('blog.article', ['article' => $article]),
                'image' => url($article->icon),
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
