<?php

use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('returns a polished setup state instead of a raw calendar authorization error', function () {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business, 'starter');

    $this->actingAs($owner)
        ->get(route('business.calendar', $business))
        ->assertConflict()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Access/Unavailable')
            ->where('state.code', 'setup_required')
            ->where('state.action.label', 'Complete salon setup')
        );
});

it('distinguishes role denial, membership location setup, and plan upgrades', function () {
    [$receptionist, $business, $membership] = createTenantMembership(StarterRole::Receptionist);
    activateTestSubscription($business, 'starter');
    $location = Location::factory()->create(['business_id' => $business->id, 'is_active' => true]);

    $this->actingAs($receptionist)
        ->getJson(route('business.calendar', $business))
        ->assertConflict()
        ->assertJsonPath('code', 'setup_required')
        ->assertJsonPath('action', null);

    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);

    $this->actingAs($receptionist)
        ->getJson(route('business.billing.show', $business))
        ->assertForbidden()
        ->assertJsonPath('code', 'permission_denied');

    [$owner, $starterBusiness] = createTenantMembership();
    activateTestSubscription($starterBusiness, 'starter');
    Location::factory()->create(['business_id' => $starterBusiness->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->getJson(route('business.inventory.index', $starterBusiness))
        ->assertPaymentRequired()
        ->assertJsonPath('code', 'upgrade_required')
        ->assertJsonPath('action.label', 'View plans');
});

it('shares navigation decisions that agree with backend feature enforcement', function () {
    [$receptionist, $business, $membership] = createTenantMembership(StarterRole::Receptionist);
    activateTestSubscription($business, 'starter');
    $location = Location::factory()->create(['business_id' => $business->id, 'is_active' => true]);
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);

    $this->actingAs($receptionist)
        ->get(route('business.dashboard', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.features.calendar.status', 'available')
            ->where('tenant.features.walk-in-queue.status', 'available')
            ->where('tenant.features.subscription-billing.visible', false)
            ->where('tenant.features.settings.visible', false)
        );
});
