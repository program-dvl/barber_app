<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SolutionController extends Controller
{
    public function index(): Response
    {
        $solutions = collect(config('frontsite.solutions'))
            ->map(fn (array $solution, string $slug): array => [
                'slug' => $slug,
                'label' => $solution['label'],
                'title' => $solution['title'],
                'description' => $solution['description'],
                'image' => $solution['image'],
                'image_alt' => $solution['image_alt'],
                'accent' => $solution['accent'],
            ])->values();

        return Inertia::render('Marketing/Solutions/Index', [
            'solutions' => $solutions,
            'seo' => [
                'title' => 'Software for appointment-led service businesses | ClipperDesk',
                'description' => 'Explore ClipperDesk for beauty, wellness, personal care, fitness, recovery, health and pet-service businesses, from independents to larger teams.',
                'canonical' => route('marketing.solutions'),
                'image' => url('/images/marketing/industries/fitness-recovery.webp'),
            ],
        ]);
    }

    public function show(string $solution): Response
    {
        $solutions = config('frontsite.solutions');
        abort_unless(isset($solutions[$solution]), 404);
        $content = $solutions[$solution];
        $features = config('frontsite.features');

        return Inertia::render('Marketing/Solutions/Show', [
            'solution' => [...$content, 'slug' => $solution],
            'features' => collect($content['features'])->map(fn (string $slug): array => [
                'slug' => $slug,
                'label' => $features[$slug]['label'],
                'title' => $features[$slug]['title'],
            ])->values(),
            'seo' => [
                'title' => $content['seo_title'],
                'description' => $content['description'],
                'canonical' => route('marketing.solutions.show', $solution),
                'image' => url($content['image']),
            ],
        ]);
    }
}
