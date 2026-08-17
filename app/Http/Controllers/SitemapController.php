<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function xml(): Response
    {
        $urls = collect([route('marketing.home'), route('marketing.features'), route('marketing.solutions'), route('marketing.use-cases'), route('marketing.pricing'), route('marketing.security'), route('marketing.company'), route('marketing.resources'), route('blog.index')])
            ->merge(collect(array_keys(config('frontsite.features')))->map(fn ($slug) => route('marketing.features.show', $slug)))
            ->merge(collect(array_keys(config('frontsite.solutions')))->map(fn ($slug) => route('marketing.solutions.show', $slug)))
            ->merge(collect(array_keys(config('frontsite.use_cases')))->map(fn ($slug) => route('marketing.use-cases.show', $slug)))
            ->merge(collect(array_keys(config('frontsite.guides')))->map(fn ($slug) => route('marketing.guides.show', $slug)))
            ->merge(Article::query()->publishable()->pluck('slug')->map(fn ($slug) => route('blog.article', $slug)))
            ->unique()->sort()->values();

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $body .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
        }
        $body .= '</urlset>'."\n";

        return response($body)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function legacy(): RedirectResponse
    {
        return redirect()->route('sitemap.xml', status: 301);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\nSitemap: ".route('sitemap.xml')."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
