<?php

namespace App\Http\Controllers;

use App\Support\Seo\ProductEvidence;
use App\Support\Seo\PublicPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return PublicPage::render('Home', [
            'homepage' => config('homepage'),
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'screenshots' => app(ProductEvidence::class)->screenshots(['calendar']),
            'seo' => [
                'title' => config('homepage.title'),
                'description' => config('homepage.description'),
                'canonical' => route('marketing.home'),
            ],
        ]);
    }
}
