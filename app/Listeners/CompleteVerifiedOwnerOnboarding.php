<?php

namespace App\Listeners;

use App\Domain\Billing\Services\OwnerOnboardingService;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Verified;

class CompleteVerifiedOwnerOnboarding
{
    public function __construct(private readonly OwnerOnboardingService $onboarding) {}

    public function handle(Verified $event): void
    {
        $business = $this->onboarding->complete($event->user);

        if (! $business) {
            return;
        }

        $subscription = $business->subscription()->firstOrFail();
        $timeZone = $business->time_zone ?: config('app.timezone', 'UTC');
        $trialEndsAt = $subscription->trial_ends_at
            ? $subscription->trial_ends_at->timezone($timeZone)->format('M j, Y \a\t g:i A T')
            : 'the date shown in Billing';

        $event->user->notify(new WelcomeNotification(
            businessId: $business->getKey(),
            businessPublicId: $business->public_id,
            businessName: $business->name,
            trialEndsAt: $trialEndsAt,
        ));
    }
}
