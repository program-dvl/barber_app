<?php

namespace App\Http\Controllers;

use App\Support\Seo\PublicSitemap;
use App\Support\Seo\PublicUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function xml(PublicSitemap $sitemap): Response
    {
        return response($sitemap->xml())->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function legacy(): RedirectResponse
    {
        return redirect()->route('sitemap.xml', status: 301);
    }

    public function robots(): Response
    {
        return response("User-agent: OAI-SearchBot\nAllow: /\n\nUser-agent: ChatGPT-User\nAllow: /\n\nUser-agent: *\nAllow: /\n\nSitemap: ".PublicUrl::canonical(route('sitemap.xml'))."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
