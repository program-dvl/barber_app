<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Marketing/Resources/Index', [
            'guides' => collect(config('frontsite.guides'))->map(fn (array $guide, string $slug) => [
                'slug' => $slug, 'title' => $guide['title'], 'description' => $guide['description'], 'topic' => $guide['topic'],
            ])->values(),
            'articleCount' => Article::query()->publishable()->count(),
            'seo' => ['title' => 'Good Hours resources for a clearer salon day', 'description' => 'Durable operating guides and reviewed editorial articles for salons, barbershops, stylists and small spas.', 'canonical' => route('marketing.resources')],
        ]);
    }

    public function guide(string $guide): Response
    {
        $guides = config('frontsite.guides');
        abort_unless(isset($guides[$guide]), 404);
        $content = $guides[$guide];

        return Inertia::render('Marketing/Resources/Guide', [
            'guide' => [...$content, 'slug' => $guide],
            'seo' => ['title' => $content['title'].' | Good Hours', 'description' => $content['description'], 'canonical' => route('marketing.guides.show', $guide)],
        ]);
    }
}
