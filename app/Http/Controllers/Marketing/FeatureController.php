<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\Seo\PublicPage;
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
            ])
            ->values();

        return PublicPage::render('Marketing/Features/Index', [
            'features' => $features,
            'seo' => [
                'title' => 'Service business management software features | ClipperDesk',
                'description' => 'Explore online booking, staff calendars, walk-ins, client management, checkout and reporting for appointment-led service businesses.',
                'canonical' => route('marketing.features'),
                'image' => url('/images/marketing/editorial/product-workday.webp'),
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

        return PublicPage::render('Marketing/Features/Show', [
            'feature' => [...collect($content)->except('requirements')->all(), 'slug' => $feature],
            'related' => $related,
            'seo' => [
                'title' => $content['seo_title'],
                'description' => $content['description'],
                'canonical' => route('marketing.features.show', $feature),
                'image' => url($content['image']),
            ],
        ]);
    }
}
