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
                'title' => 'ClipperDesk pricing for salons and barbershops',
                'description' => 'Compare the current server-owned ClipperDesk Starter and Pro catalog, trial mechanics, limits and provider qualifications.',
                'canonical' => route('marketing.pricing'),
            ],
        ]);
    }
}
