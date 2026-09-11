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
                'title' => 'Business software for beauty, wellness and appointment-led teams',
                'description' => 'Explore how ClipperDesk supports the daily workflow of salons, barbershops, spas, studios and appointment-led service businesses.',
                'canonical' => route('marketing.solutions'),
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
                'title' => $content['title'].' | ClipperDesk',
                'description' => $content['description'],
                'canonical' => route('marketing.solutions.show', $solution),
            ],
        ]);
    }
}
