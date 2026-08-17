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
                'title' => 'Good Hours pricing for salons and barbershops',
                'description' => 'Compare the current server-owned Good Hours Starter and Pro catalog, trial mechanics, limits and provider qualifications.',
                'canonical' => route('marketing.pricing'),
            ],
        ]);
    }
}
