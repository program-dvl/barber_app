<?php

namespace App\Support\Seo;

use App\Domain\HelpCenter\HelpCatalog;
use App\Models\Article;
use App\Support\Legal\PrivacyNotice;

class PublicSitemap
{
    public function xml(): string
    {
        $urls = collect([route('marketing.home'), route('marketing.features'), route('marketing.solutions'), route('marketing.use-cases'), route('marketing.pricing'), route('marketing.security'), route('marketing.company'), route('marketing.resources'), route('blog.index')])
            ->merge(collect(array_keys(config('frontsite.features')))->map(fn ($slug) => route('marketing.features.show', $slug)))
            ->merge(collect(array_keys(config('frontsite.solutions')))->map(fn ($slug) => route('marketing.solutions.show', $slug)))
            ->merge(collect(array_keys(config('frontsite.use_cases')))->map(fn ($slug) => route('marketing.use-cases.show', $slug)))
            ->merge(collect(array_keys(config('frontsite.guides')))->map(fn ($slug) => route('marketing.guides.show', $slug)))
            ->merge([route('help.index')])
            ->merge(collect(app(HelpCatalog::class)->categories())->filter(fn ($category) => $category['article_count'] > 0)->pluck('href'))
            ->merge(collect(app(HelpCatalog::class)->summaries())->pluck('href'))
            ->merge(Article::query()->publishable()->pluck('slug')->map(fn ($slug) => route('blog.article', $slug)))
            ->map(fn (string $url) => PublicUrl::canonical($url))->unique()->sort()->values();

        if (app(PrivacyNotice::class)->isPublished()) {
            $urls = $urls->push(config('privacy.canonical_url'))->unique()->sort()->values();
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $body .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
        }

        return $body.'</urlset>'."\n";
    }
}
