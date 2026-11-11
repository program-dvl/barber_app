<?php

use App\Http\Controllers\Marketplace\CustomerAccountController;
use App\Http\Controllers\Marketplace\CustomerAuthController;
use App\Http\Controllers\Marketplace\CustomerGoogleController;
use App\Http\Controllers\Marketplace\DiscoveryController;
use App\Http\Controllers\Marketplace\SalonController;
use App\Http\Controllers\PublicBooking\PublicBookingFlowController;
use App\Http\Middleware\MarketplaceSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('marketplace')->name('marketplace.')->group(function () {
    Route::get('/', DiscoveryController::class)->middleware('throttle:60,1')->name('discover');
    Route::post('/nearby', [DiscoveryController::class, 'nearby'])->middleware('throttle:10,1')->name('nearby');
    Route::delete('/nearby', [DiscoveryController::class, 'clearNearby'])->name('nearby.clear');
    Route::post('/acquisition/observe', [\App\Http\Controllers\Marketplace\AcquisitionController::class, 'observe'])->middleware('throttle:20,1')->name('acquisition.observe');
    Route::delete('/acquisition', [\App\Http\Controllers\Marketplace\AcquisitionController::class, 'forget'])->name('acquisition.forget');
    Route::get('/services', [DiscoveryController::class, 'services'])->middleware('throttle:30,1')->name('services');
    Route::get('/terms', fn () => Inertia::render('Marketplace/Terms'))->name('terms');
    Route::get('/auth/google', [CustomerGoogleController::class, 'redirect'])->middleware('throttle:10,1')->name('google');
    Route::get('/sign-in', [CustomerAuthController::class, 'show'])->name('sign-in');
    Route::post('/sign-in', [CustomerAuthController::class, 'send'])->middleware('throttle:5,1')->name('sign-in.send');
    Route::get('/sign-in/link/{token}', [CustomerAuthController::class, 'preview'])->where('token', '[a-f0-9]{64}')->name('sign-in.link');
    Route::get('/sign-in/confirm', [CustomerAuthController::class, 'confirmation'])->name('sign-in.confirm');
    Route::post('/sign-in/confirm', [CustomerAuthController::class, 'consume'])->middleware('throttle:10,1')->name('sign-in.consume');
    Route::post('/sign-out', [CustomerAuthController::class, 'logout'])->name('sign-out');
    Route::get('/salons/{slug}', [SalonController::class, 'show'])->name('salon');
    Route::get('/salons/{slug}/book', [SalonController::class, 'book'])->name('book');
    Route::post('/salons/{slug}/times', [SalonController::class, 'availability'])->middleware('throttle:20,1')->name('times');
    Route::prefix('/salons/{slug}/booking')->name('booking.')->middleware('throttle:20,1')->group(function () {
        foreach (['start', 'search', 'hold', 'resume', 'details', 'release', 'confirm'] as $action) {
            Route::post('/'.$action, [PublicBookingFlowController::class, $action])->name($action);
        }
    });
    Route::middleware(MarketplaceSession::class)->group(function () {
        Route::get('/messages', [CustomerAccountController::class, 'inbox'])->name('inbox');
        Route::get('/account', [CustomerAccountController::class, 'index'])->name('account');
        Route::get('/profile/photo', [CustomerAccountController::class, 'photo'])->name('profile.photo');
        Route::post('/profile/photo', [CustomerAccountController::class, 'savePhoto'])->middleware('throttle:10,1')->name('profile.photo.save');
        Route::delete('/profile/photo', [CustomerAccountController::class, 'removePhoto'])->name('profile.photo.remove');
        Route::get('/profile', [CustomerAccountController::class, 'showProfile'])->name('profile');
        Route::patch('/profile', [CustomerAccountController::class, 'profile'])->name('profile.update');
        Route::patch('/account', [CustomerAccountController::class, 'profile'])->name('account.update');
        Route::post('/account/bookings', [CustomerAccountController::class, 'claim'])->middleware('throttle:5,1')->name('claim');
        Route::post('/account/bookings/{appointment}/manage', [CustomerAccountController::class, 'manage'])->middleware('throttle:20,1')->name('manage');
        Route::get('/account/bookings/{appointment}/messages', [CustomerAccountController::class, 'conversation'])->name('messages');
        Route::post('/account/bookings/{appointment}/messages', [CustomerAccountController::class, 'message'])->middleware('throttle:10,1')->name('messages.send');
        Route::patch('/account/bookings/{appointment}/messages', [CustomerAccountController::class, 'pause'])->name('messages.pause');
        Route::post('/account/bookings/{appointment}/report', [CustomerAccountController::class, 'report'])->middleware('throttle:3,1')->name('report');
        Route::post('/salons/{slug}/favourite', [CustomerAccountController::class, 'favourite'])->middleware('throttle:20,1')->name('favourite');
    });
    Route::get('/sitemap.xml', [\App\Http\Controllers\Marketplace\GrowthSitemapController::class, 'index'])->name('growth.sitemap');
    Route::get('/sitemaps/{kind}/{shard}.xml', [\App\Http\Controllers\Marketplace\GrowthSitemapController::class, 'shard'])->where('kind', 'profiles|hubs')->whereNumber('shard')->name('growth.sitemap.shard');
    Route::get('/{country}', \App\Http\Controllers\Marketplace\LocalPageController::class)->where('country', '[a-z]{2}')->name('local.country');
    Route::get('/{country}/{region}/{city}/{audience?}', \App\Http\Controllers\Marketplace\LocalPageController::class)->where(['country' => '[a-z]{2}', 'region' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+', 'audience' => '[a-z0-9-]+'])->name('local');
    Route::get('/{country}/{region}/{city}/services/{concept}', \App\Http\Controllers\Marketplace\LocalPageController::class)->where(['country' => '[a-z]{2}', 'region' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+', 'concept' => '[a-z0-9-]+'])->name('local.service');

});
