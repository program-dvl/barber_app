<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;

interface SubscriptionProvider
{
    /** @return array{url: string, provider_session_id: string} */
    public function createCheckout(Business $business, BillingPlanPrice $price, string $successUrl, string $cancelUrl, ?string $couponCode = null): array;

    public function changePrice(BusinessSubscription $subscription, BillingPlanPrice $price, bool $atPeriodEnd): void;

    public function cancelAtPeriodEnd(BusinessSubscription $subscription): void;

    public function cancelImmediately(BusinessSubscription $subscription): void;

    public function reactivate(BusinessSubscription $subscription): void;

    public function billingPortalUrl(BusinessSubscription $subscription, string $returnUrl): string;
}
