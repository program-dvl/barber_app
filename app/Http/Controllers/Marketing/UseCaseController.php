<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class UseCaseController extends Controller
{
    public function index(): Response
    {
        $useCases = collect(config('frontsite.use_cases'))
            ->map(fn (array $useCase, string $slug): array => [
                'slug' => $slug,
                'label' => $useCase['label'],
                'title' => $useCase['title'],
                'description' => $useCase['description'],
            ])
            ->values();

        return Inertia::render('Marketing/UseCases/Index', [
            'useCases' => $useCases,
            'seo' => [
                'title' => 'Practical salon operating use cases | Good Hours',
                'description' => 'Practical guidance for scheduling conflicts, mixed walk-ins, deposits and connected client history, with honest Good Hours workflows and limits.',
                'canonical' => route('marketing.use-cases'),
            ],
        ]);
    }

    public function show(string $useCase): Response
    {
        $useCases = config('frontsite.use_cases');
        abort_unless(isset($useCases[$useCase]), 404);

        $content = $useCases[$useCase];
        $features = config('frontsite.features');
        $solutions = config('frontsite.solutions');

        return Inertia::render('Marketing/UseCases/Show', [
            'useCase' => [...$content, 'slug' => $useCase],
            'feature' => [
                'slug' => $content['feature'],
                'label' => $features[$content['feature']]['label'],
                'title' => $features[$content['feature']]['title'],
            ],
            'solution' => [
                'slug' => $content['solution'],
                'label' => $solutions[$content['solution']]['label'],
                'title' => $solutions[$content['solution']]['title'],
            ],
            'seo' => [
                'title' => $content['title'].' | Good Hours',
                'description' => $content['description'],
                'canonical' => route('marketing.use-cases.show', $useCase),
            ],
        ]);
    }
}
