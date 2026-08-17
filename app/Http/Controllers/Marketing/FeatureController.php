<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    public function index(): Response
    {
        $features = collect(config('frontsite.features'))
            ->map(fn (array $feature, string $slug): array => [
                'slug' => $slug,
                'label' => $feature['label'],
                'title' => $feature['title'],
                'description' => $feature['description'],
                'requirements' => $feature['requirements'],
            ])
            ->values();

        return Inertia::render('Marketing/Features/Index', [
            'features' => $features,
            'seo' => [
                'title' => 'Good Hours features for the complete salon day',
                'description' => 'Explore connected online booking, calendar and walk-ins, client management, checkout and reporting for salons and barbershops.',
                'canonical' => route('marketing.features'),
            ],
        ]);
    }

    public function show(string $feature): Response
    {
        $features = config('frontsite.features');
        abort_unless(isset($features[$feature]), 404);

        $content = $features[$feature];
        $related = collect($content['related'])
            ->map(fn (string $slug): array => [
                'slug' => $slug,
                'label' => $features[$slug]['label'],
                'title' => $features[$slug]['title'],
            ])
            ->values();

        return Inertia::render('Marketing/Features/Show', [
            'feature' => [...$content, 'slug' => $feature],
            'related' => $related,
            'seo' => [
                'title' => $content['title'].' | Good Hours',
                'description' => $content['description'],
                'canonical' => route('marketing.features.show', $feature),
            ],
        ]);
    }
}
