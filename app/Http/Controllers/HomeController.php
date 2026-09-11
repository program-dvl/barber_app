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
                'title' => 'Salon, barber and wellness business software | ClipperDesk',
                'description' => 'ClipperDesk connects booking, scheduling, clients, staff, checkout and reporting for modern beauty, wellness and appointment-led businesses.',
                'canonical' => route('marketing.home'),
            ],
        ]);
    }
}
