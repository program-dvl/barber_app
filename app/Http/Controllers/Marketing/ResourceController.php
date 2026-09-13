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
            'seo' => ['title' => 'Service business management guides | ClipperDesk', 'description' => 'Practical booking, scheduling and operations guides for appointment-led beauty, wellness, fitness, health and pet-service businesses.', 'canonical' => route('marketing.resources'), 'image' => url('/images/marketing/editorial/resources-planning.webp')],
        ]);
    }

    public function guide(string $guide): Response
    {
        $guides = config('frontsite.guides');
        abort_unless(isset($guides[$guide]), 404);
        $content = $guides[$guide];

        return Inertia::render('Marketing/Resources/Guide', [
            'guide' => [...$content, 'slug' => $guide],
            'seo' => ['title' => $content['seo_title'], 'description' => $content['description'], 'canonical' => route('marketing.guides.show', $guide), 'image' => url($content['image'])],
        ]);
    }
}
