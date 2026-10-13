<?php

namespace App\Http\Controllers\Marketing;

use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Http\Controllers\Controller;
use App\Support\Seo\PublicPage;
use Inertia\Response;

class PricingController extends Controller
{
    public function __invoke(PublicPricingCatalog $catalog): Response
    {
        return PublicPage::render('Marketing/Pricing', [
            'catalog' => $catalog->present(),
            'seo' => [
                'title' => 'Pricing for your locations and team | ClipperDesk',
                'description' => 'One complete ClipperDesk subscription, with transparent location pricing and progressive staff rates. Explore monthly or yearly billing and start a free trial.',
                'canonical' => route('marketing.pricing'),
                'image' => url('/images/marketing/editorial/pricing-decision.webp'),
            ],
        ]);
    }
}
