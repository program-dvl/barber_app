<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Home', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'seo' => [
                'title' => 'Salon and barbershop software from booking to checkout',
                'description' => 'Good Hours connects online booking, the daily calendar, clients, checkout and clear reporting for salons and barbershops.',
                'canonical' => route('marketing.home'),
            ],
        ]);
    }
}
