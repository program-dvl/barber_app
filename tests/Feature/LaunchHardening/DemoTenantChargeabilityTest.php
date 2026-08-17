<?php

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Database\Seeders\GoodHoursDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a representative shop with an accessible trial billing journey', function () {
    $this->seed(GoodHoursDemoSeeder::class);

    $business = Business::query()->where('slug', 'good-hours-demo-tenant')->firstOrFail();
    $owner = User::query()->where('email', 'owner@pine-palm.example.test')->firstOrFail();
    $subscription = BusinessSubscription::query()->whereBelongsTo($business)->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_started_at)->not->toBeNull()
        ->and($subscription->trial_ends_at->isAfter($subscription->trial_started_at))->toBeTrue();

    $this->actingAs($owner)
        ->get(route('business.billing.show', $business))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/Overview')
            ->where('businessLabel', 'Pine & Palm Studio')
            ->where('subscription.status', SubscriptionStatus::Trialing->value));
});
