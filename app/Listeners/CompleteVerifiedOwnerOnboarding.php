<?php

namespace App\Listeners;

use App\Domain\Billing\Services\OwnerOnboardingService;
use Illuminate\Auth\Events\Verified;

class CompleteVerifiedOwnerOnboarding
{
    public function __construct(private readonly OwnerOnboardingService $onboarding) {}

    public function handle(Verified $event): void
    {
        $this->onboarding->complete($event->user);
    }
}
