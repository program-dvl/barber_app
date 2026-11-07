<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\Seo\ProductEvidence;
use App\Support\Seo\PublicPage;
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

        return PublicPage::render('Marketing/Solutions/Index', [
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

        return PublicPage::render('Marketing/Solutions/Show', [
            'solution' => [...collect($content)->except('requirements')->all(), 'slug' => $solution, 'screenshots' => app(ProductEvidence::class)->screenshots($content['screens'] ?? [])],
            'guides' => Article::query()->publishable()
                ->whereIn('slug', match ($solution) {
                    'salons' => ['salon-consultation-notes-a-practical-template-and-example', 'how-to-increase-salon-rebooking-scripts-and-a-30-day-plan', 'how-to-reduce-salon-no-shows-10-practical-strategies-that-work'],
                    'barbershops' => ['how-to-manage-barbershop-walk-ins-without-delaying-bookings', 'how-to-run-a-successful-salon-or-barbershop-in-2027-a-complete-guide'],
                    default => [],
                })->latest('published_at')->get(['title', 'slug', 'excerpt'])
                ->map(fn (Article $article): array => ['title' => $article->title, 'excerpt' => $article->excerpt, 'href' => route('blog.article', $article->slug)]),
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
