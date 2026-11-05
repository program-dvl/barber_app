<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\Seo\ProductEvidence;
use App\Support\Seo\PublicPage;
use Inertia\Response;

class ProductTourController extends Controller
{
    public function __invoke(ProductEvidence $evidence): Response
    {
        return PublicPage::render('Marketing/ProductTour', [
            'steps' => collect(config('product-tour.steps'))->map(fn (array $step) => [
                ...$step, 'screenshots' => $evidence->screenshots([$step['id']]),
            ])->values(),
            'seo' => [
                'title' => 'Product walkthrough: booking to checkout | ClipperDesk',
                'description' => 'See real ClipperDesk screens for online booking, calendars, walk-ins, client history and recorded checkout. Explore the workflow before starting a trial.',
                'canonical' => route('marketing.product-tour'),
                'image' => url('/images/help/calendar.webp'),
            ],
        ]);
    }
}
