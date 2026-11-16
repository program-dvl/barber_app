<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\Seo\PublicPage;
use Inertia\Response;

class NoShowCalculatorController extends Controller
{
    public function __invoke(): Response
    {
        return PublicPage::render('Marketing/Tools/NoShowCalculator', [
            'toolFallback' => true,
            'seo' => [
                'title' => 'Free No-Show Cost Calculator | ClipperDesk',
                'description' => 'Estimate the monthly and yearly cost of missed appointments. Adjust for refilled slots, retained payments and avoided costs. Free, with no signup required.',
                'canonical' => route('marketing.tools.no-show'),
            ],
        ]);
    }
}
