<?php

use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\BusinessConfiguration\Services\StarterWorkspaceProvisioner;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PublicBooking\Models\PublicBookingFlow;
use App\Support\Files\TenantFilePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
});

it('renders the actual private catalog without publishing reviewing or creating a flow', function () {
    [$owner, $business, $membership] = createTenantMembership();
    activateTestSubscription($business);
    app(OnboardingManager::class)->resume($business);
    $business = app(StarterWorkspaceProvisioner::class)->provision($business, $membership, $owner, [
        'business_type' => 'hair_salon', 'operation_model' => 'at_location', 'team_size' => '1',
        'location_scale' => 'single', 'owner_bookable' => true, 'country_code' => 'CA',
        'time_zone' => 'America/Toronto', 'address' => '18 Preview Lane', 'phone' => '',
        'schedule_preset' => 'daily_9_21', 'service_keys' => ['cut_finish'],
    ]);
    $audits = AuditEvent::query()->count();
    $this->actingAs($owner)->get(route('business.configuration.booking-preview', $business))
        ->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertInertia(fn (Assert $page) => $page->component('Booking/Welcome')->where('preview', true)->where('business.name', $business->name)->has('catalog.services', 1)->has('catalog.locations', 1)->where('catalog.locations.0.address', '18 Preview Lane'));
    expect($business->fresh()->configuration_published_at)->toBeNull()
        ->and($business->onboardingSession->previewed_at)->toBeNull()
        ->and(PublicBookingFlow::query()->count())->toBe(0)->and(AuditEvent::query()->count())->toBe($audits);
    $this->get(route('booking.business', $business->booking_slug))->assertNotFound();
    $this->postJson(route('public.booking.start', $business->booking_slug))->assertNotFound();
});

it('keeps preview and branding private to authorized business settings members', function () {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    [$outsider] = createTenantMembership();
    [$receptionist] = createTenantMembership(StarterRole::Receptionist, $business);
    Storage::fake('private');
    $business->update(['logo_path' => 'configuration/branding/example.png']);
    Storage::disk('private')->put(TenantFilePath::private($business, $business->logo_path), 'example-image');
    $preview = route('business.configuration.booking-preview', $business);
    $media = route('business.configuration.booking-preview.media', [$business, 'logo']);
    $this->get($preview)->assertRedirect(route('login'));
    $this->get($media)->assertRedirect(route('login'));
    $this->actingAs($outsider)->get($preview)->assertForbidden();
    $this->get($media)->assertForbidden();
    $this->actingAs($receptionist)->get($preview)->assertForbidden();
    $this->get($media)->assertForbidden();
    $this->actingAs($owner)->get($media)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
    $this->get(route('business.configuration.show', $business))->assertHeader('X-Frame-Options', 'DENY');
});

it('allows an empty not-yet-prepared business to preview without a booking slug', function () {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    $business->update(['booking_slug' => null]);
    $this->actingAs($owner)->get(route('business.configuration.booking-preview', $business))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Booking/Welcome')->where('preview', true)->where('business.booking_url', null)->has('catalog.locations', 0));
    $this->assertDatabaseCount('commerce_settings', 0);
});
