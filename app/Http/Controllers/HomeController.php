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
                'title' => 'Appointment scheduling & service business software | ClipperDesk',
                'description' => 'Run booking, staff calendars, client records, walk-ins, checkout and reporting in one workspace for beauty, wellness, fitness, health and pet-care businesses.',
                'canonical' => route('marketing.home'),
            ],
        ]);
    }
}
