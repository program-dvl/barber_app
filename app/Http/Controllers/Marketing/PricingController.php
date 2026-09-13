<?php

namespace App\Http\Controllers\Marketing;

use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function __invoke(PublicPricingCatalog $catalog): Response
    {
        return Inertia::render('Marketing/Pricing', [
            'catalog' => $catalog->present(),
            'seo' => [
                'title' => 'Service business software pricing | ClipperDesk',
                'description' => 'Compare ClipperDesk plans, trial terms and operating limits for appointment-led beauty, wellness, fitness, health and pet-service businesses.',
                'canonical' => route('marketing.pricing'),
                'image' => url('/images/marketing/editorial/pricing-decision.webp'),
            ],
        ]);
    }
}
