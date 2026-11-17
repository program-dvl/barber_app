<?php

namespace App\Support\Seo;

use App\Models\Article;

class RelatedReading
{
    public function forFeature(string $feature): array
    {
        $slugs = match ($feature) {
            'client-management' => ['salon-consultation-notes-a-practical-template-and-example', 'how-to-increase-salon-rebooking-scripts-and-a-30-day-plan'],
            'calendar-and-walk-ins' => ['how-to-manage-barbershop-walk-ins-without-delaying-bookings'],
            'online-booking' => ['how-to-reduce-salon-no-shows-10-practical-strategies-that-work'],
            default => [],
        };
        $reading = Article::query()->publishable()->whereIn('slug', $slugs)->get(['title', 'slug', 'excerpt'])
            ->map(fn (Article $article) => ['title' => $article->title, 'description' => $article->excerpt, 'href' => route('blog.article', $article->slug)])
            ->all();
        $guides = match ($feature) {
            'client-management' => ['switch-from-paper-bookings'],
            'calendar-and-walk-ins' => ['service-timing-and-buffers', 'salon-software-demo-scorecard', 'barber-walk-in-handover-sheet'],
            'online-booking' => ['service-timing-and-buffers', 'salon-booking-slot-exercise', 'salon-no-show-response-planner'],
            default => [],
        };
        foreach ($guides as $slug) {
            $guide = config('frontsite.guides.'.$slug);
            if ($guide) {
                $reading[] = ['title' => $guide['title'], 'description' => $guide['description'], 'href' => route('marketing.guides.show', $slug)];
            }
        }

        return $reading;
    }
}
