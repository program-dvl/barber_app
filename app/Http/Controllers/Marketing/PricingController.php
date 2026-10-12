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
                'title' => 'Pricing for your locations and team | ClipperDesk',
                'description' => 'Calculate your ClipperDesk subscription by location and bookable staff. One plan, monthly or yearly billing, and a free trial without a credit card.',
                'canonical' => route('marketing.pricing'),
                'image' => url('/images/marketing/editorial/pricing-decision.webp'),
            ],
        ]);
    }
}
