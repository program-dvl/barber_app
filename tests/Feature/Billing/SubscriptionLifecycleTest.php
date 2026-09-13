<?php

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\StripeWebhookProcessor;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('starts business-owned trials on Stripe and restricts expired trials without deleting data', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $subscription->update(['trial_ends_at' => now()->subMinute()]);

    $this->artisan('billing:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Restricted)
        ->and($subscription->fresh()->restriction_level)->toBe(RestrictionLevel::ReadOnly)
        ->and(DB::table('billing_notices')->where('type', 'trial_expired')->count())->toBe(1)
        ->and($business->fresh())->not->toBeNull();

    $this->actingAs($owner)
        ->postJson(route('business.appointments.store', $business), [])
        ->assertPaymentRequired()
        ->assertJsonPath('code', 'subscription_restricted');

    $this->actingAs($owner)->get(route('business.billing.show', $business))->assertOk();
});

it('creates one reusable hosted Stripe checkout from an approved server-side price', function () {
    [$owner, $business] = stripeTrialBusiness();
    $price = configuredStripePrice('starter', BillingInterval::Monthly);
    config([
        'billing.stripe.secret' => 'sk_test_local',
        'billing.stripe.webhook_secret' => 'whsec_test_local',
    ]);

    $provider = $this->mock(SubscriptionProvider::class, function (MockInterface $mock): void {
        $mock->shouldReceive('createCheckout')->once()
            ->withArgs(fn (Business $business, BillingPlanPrice $price, BillingCheckoutAttempt $attempt, string $success, string $cancel, ?string $coupon) => $business->exists
                && $price->provider_price_id === 'price_starter_monthly_test'
                && Str::isUlid($attempt->public_id)
                && str_contains($success, '{CHECKOUT_SESSION_ID}')
                && str_contains($cancel, 'checkout=canceled')
                && $coupon === null)
            ->andReturn(['url' => 'https://checkout.stripe.test/cs_test_approved', 'provider_session_id' => 'cs_test_approved']);
    });
    app()->instance(SubscriptionProvider::class, $provider);

    $first = $this->actingAs($owner)->postJson(route('business.billing.checkout', $business), ['price_id' => $price->getKey()]);
    $first->assertCreated()->assertJsonPath('url', 'https://checkout.stripe.test/cs_test_approved');
    $second = $this->actingAs($owner)->postJson(route('business.billing.checkout', $business), ['price_id' => $price->getKey()]);
    $second->assertOk()->assertJsonPath('attempt_id', $first->json('attempt_id'));

    expect(BillingCheckoutAttempt::query()->count())->toBe(1)
        ->and(BillingCheckoutAttempt::query()->first()->provider_transaction_id)->toBe('cs_test_approved');
});

it('expires a competing Stripe Checkout before replacing it with another plan selection', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $starter = configuredStripePrice('starter', BillingInterval::Monthly);
    $pro = configuredStripePrice('pro', BillingInterval::Monthly);
    config([
        'billing.stripe.secret' => 'sk_test_local',
        'billing.stripe.webhook_secret' => 'whsec_test_local',
    ]);
    $oldAttempt = BillingCheckoutAttempt::query()->create([
        'business_id' => $business->getKey(),
        'business_subscription_id' => $subscription->getKey(),
        'billing_plan_price_id' => $starter->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'provider' => 'stripe',
        'provider_transaction_id' => 'cs_old_selection',
        'provider_checkout_url' => 'https://checkout.stripe.test/cs_old_selection',
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);
    $provider = $this->mock(SubscriptionProvider::class, function (MockInterface $mock) use ($oldAttempt): void {
        $mock->shouldReceive('expireCheckout')->once()->withArgs(fn (BillingCheckoutAttempt $attempt) => $attempt->is($oldAttempt));
        $mock->shouldReceive('createCheckout')->once()
            ->andReturn(['url' => 'https://checkout.stripe.test/cs_new_selection', 'provider_session_id' => 'cs_new_selection']);
    });
    app()->instance(SubscriptionProvider::class, $provider);

    $this->actingAs($owner)
        ->postJson(route('business.billing.checkout', $business), ['price_id' => $pro->getKey()])
        ->assertCreated()
        ->assertJsonPath('url', 'https://checkout.stripe.test/cs_new_selection');

    expect($oldAttempt->fresh()->status)->toBe('superseded')
        ->and($oldAttempt->fresh()->expires_at->isPast())->toBeTrue()
        ->and($subscription->checkoutAttempts()->where('status', 'pending')->count())->toBe(1);
});

it('fails closed while another request is still preparing the Stripe Checkout reservation', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $price = configuredStripePrice('starter', BillingInterval::Monthly);
    config([
        'billing.stripe.secret' => 'sk_test_local',
        'billing.stripe.webhook_secret' => 'whsec_test_local',
    ]);
    BillingCheckoutAttempt::query()->create([
        'business_id' => $business->getKey(),
        'business_subscription_id' => $subscription->getKey(),
        'billing_plan_price_id' => $price->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'provider' => 'stripe',
        'provider_transaction_id' => 'pending_concurrent_request',
        'status' => 'pending',
        'expires_at' => now()->addMinutes(2),
    ]);
    $provider = $this->mock(SubscriptionProvider::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('createCheckout');
        $mock->shouldNotReceive('expireCheckout');
    });
    app()->instance(SubscriptionProvider::class, $provider);

    $this->actingAs($owner)
        ->postJson(route('business.billing.checkout', $business), ['price_id' => $price->getKey()])
        ->assertConflict()
        ->assertJsonPath('message', 'A secure checkout is already being prepared for this account. Please wait a moment and try again.');

    expect($subscription->checkoutAttempts()->count())->toBe(1);
});

it('fails closed before checkout when Stripe webhook verification is not configured', function () {
    [$owner, $business] = stripeTrialBusiness();
    $price = configuredStripePrice('starter', BillingInterval::Monthly);
    config([
        'billing.stripe.secret' => 'sk_test_local',
        'billing.stripe.webhook_secret' => null,
    ]);

    $provider = $this->mock(SubscriptionProvider::class, fn (MockInterface $mock) => $mock->shouldNotReceive('createCheckout'));
    app()->instance(SubscriptionProvider::class, $provider);

    $this->actingAs($owner)
        ->postJson(route('business.billing.checkout', $business), ['price_id' => $price->getKey()])
        ->assertServiceUnavailable();

    expect(BillingCheckoutAttempt::query()->count())->toBe(0);
});

it('rejects a forged or unconfigured Stripe price before contacting the provider', function () {
    [$owner, $business] = stripeTrialBusiness();
    $plan = BillingPlan::query()->where('code', 'starter')->firstOrFail();
    $forged = BillingPlanPrice::query()->create([
        'billing_plan_id' => $plan->getKey(),
        'billing_interval' => BillingInterval::Monthly,
        'currency' => 'USD',
        'amount_minor' => 1,
        'provider' => 'stripe',
        'provider_price_id' => 'price_forged',
        'is_active' => true,
        'effective_from' => now()->subMinute(),
    ]);
    config(['billing.stripe.secret' => 'sk_test_local']);

    $provider = $this->mock(SubscriptionProvider::class, fn (MockInterface $mock) => $mock->shouldNotReceive('createCheckout'));
    app()->instance(SubscriptionProvider::class, $provider);

    $this->actingAs($owner)
        ->postJson(route('business.billing.checkout', $business), ['price_id' => $forged->getKey()])
        ->assertUnprocessable();

    expect(BillingCheckoutAttempt::query()->count())->toBe(0);
});

it('uses signed idempotent webhooks as the authority for checkout activation and cancellation grace', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $price = configuredStripePrice('pro', BillingInterval::Monthly);
    $attempt = BillingCheckoutAttempt::query()->create([
        'business_id' => $business->getKey(),
        'business_subscription_id' => $subscription->getKey(),
        'billing_plan_price_id' => $price->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'provider' => 'stripe',
        'provider_transaction_id' => 'cs_activation',
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);
    $processor = app(StripeWebhookProcessor::class);
    $created = now()->timestamp;
    $checkout = stripeEvent('evt_checkout', 'checkout.session.completed', $created, [
        'id' => 'cs_activation',
        'object' => 'checkout.session',
        'mode' => 'subscription',
        'customer' => 'cus_activation',
        'subscription' => 'sub_activation',
        'client_reference_id' => $business->public_id,
        'metadata' => [
            'application' => 'clipperdesk',
            'business_public_id' => $business->public_id,
            'billing_checkout_attempt_id' => $attempt->public_id,
            'plan_price_id' => (string) $price->getKey(),
        ],
    ]);
    $active = stripeSubscriptionEvent('evt_active', $created + 1, 'active', $price, $business, false);

    $processor->receiveVerified($checkout);
    $processor->receiveVerified($active);
    $processor->receiveVerified($active);

    expect($attempt->fresh()->status)->toBe('confirmed')
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->billing_plan_id)->toBe($price->billing_plan_id)
        ->and(BillingProviderEvent::query()->count())->toBe(2)
        ->and(BillingProviderEvent::query()->where('provider_event_id', 'evt_active')->value('attempts'))->toBe(1);

    $processor->receiveVerified(stripeSubscriptionEvent('evt_cancel_scheduled', $created + 2, 'active', $price, $business, true));
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::CancelScheduled)
        ->and($subscription->fresh()->cancel_at)->not->toBeNull();
});

it('converges when Stripe delivers subscription state before the Checkout completion event', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $price = configuredStripePrice('pro', BillingInterval::Monthly);
    $attempt = BillingCheckoutAttempt::query()->create([
        'business_id' => $business->getKey(),
        'business_subscription_id' => $subscription->getKey(),
        'billing_plan_price_id' => $price->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'provider' => 'stripe',
        'provider_transaction_id' => 'cs_out_of_order',
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);
    $created = now()->timestamp;
    $processor = app(StripeWebhookProcessor::class);

    $processor->receiveVerified(
        stripeSubscriptionEvent('evt_subscription_first', $created + 1, 'active', $price, $business, false, $attempt)
    );
    $processor->receiveVerified(stripeEvent('evt_checkout_second', 'checkout.session.completed', $created, [
        'id' => 'cs_out_of_order',
        'object' => 'checkout.session',
        'mode' => 'subscription',
        'customer' => 'cus_activation',
        'subscription' => 'sub_activation',
        'client_reference_id' => $business->public_id,
        'metadata' => [
            'application' => 'clipperdesk',
            'business_public_id' => $business->public_id,
            'billing_checkout_attempt_id' => $attempt->public_id,
            'plan_price_id' => (string) $price->getKey(),
        ],
    ]));

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->provider_subscription_id)->toBe('sub_activation')
        ->and($attempt->fresh()->status)->toBe('confirmed')
        ->and($attempt->fresh()->provider_subscription_id)->toBe('sub_activation')
        ->and($attempt->fresh()->confirmed_at)->not->toBeNull();
});

it('does not activate a superseded Checkout attempt delivered late by Stripe', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $price = configuredStripePrice('pro', BillingInterval::Monthly);
    $attempt = BillingCheckoutAttempt::query()->create([
        'business_id' => $business->getKey(),
        'business_subscription_id' => $subscription->getKey(),
        'billing_plan_price_id' => $price->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'provider' => 'stripe',
        'provider_transaction_id' => 'cs_superseded',
        'status' => 'superseded',
        'expires_at' => now(),
    ]);
    $created = now()->timestamp;
    $processor = app(StripeWebhookProcessor::class);

    $providerEvent = $processor->receiveVerified(
        stripeSubscriptionEvent('evt_superseded_subscription', $created + 1, 'active', $price, $business, false, $attempt)
    );
    $checkoutEvent = $processor->receiveVerified(stripeEvent('evt_superseded_checkout', 'checkout.session.completed', $created, [
        'id' => 'cs_superseded',
        'object' => 'checkout.session',
        'mode' => 'subscription',
        'customer' => 'cus_stale',
        'subscription' => 'sub_stale',
        'client_reference_id' => $business->public_id,
        'metadata' => [
            'application' => 'clipperdesk',
            'business_public_id' => $business->public_id,
            'billing_checkout_attempt_id' => $attempt->public_id,
            'plan_price_id' => (string) $price->getKey(),
        ],
    ]));

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->fresh()->provider_subscription_id)->toBeNull()
        ->and($attempt->fresh()->status)->toBe('superseded')
        ->and($attempt->fresh()->last_error)->toBe('completed_after_checkout_was_closed')
        ->and($providerEvent->status)->toBe('ignored')
        ->and($checkoutEvent->status)->toBe('ignored');
});

it('moves renewal failures through past due and read-only restriction then recovers on payment', function () {
    [, $business, $subscription] = stripeTrialBusiness();
    $price = configuredStripePrice('pro', BillingInterval::Monthly);
    $subscription = app(SubscriptionLifecycleManager::class)->activate(
        $subscription,
        $price,
        now()->subMonth(),
        now()->addMonth(),
        now()->subMinute(),
        'cus_dunning',
        'sub_dunning',
    );
    $processor = app(StripeWebhookProcessor::class);
    $created = now()->timestamp;

    $processor->receiveVerified(stripeInvoiceEvent('evt_failed', 'invoice.payment_failed', $created, 'sub_dunning', 2));
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Grace)
        ->and($subscription->fresh()->grace_ends_at)->not->toBeNull();

    $subscription->update(['grace_ends_at' => now()->subSecond()]);
    app(SubscriptionLifecycleManager::class)->advanceDunning(now());
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Restricted)
        ->and($subscription->fresh()->restriction_level)->toBe(RestrictionLevel::ReadOnly);

    $processor->receiveVerified(stripeInvoiceEvent('evt_recovered', 'invoice.paid', $created + 2, 'sub_dunning', 3));
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->restriction_level)->toBe(RestrictionLevel::None)
        ->and(DB::table('billing_payments')->where('provider_payment_id', 'pi_evt_recovered')->value('status'))->toBe('succeeded');
});

it('projects current Stripe invoice payloads that nest subscription ownership under parent metadata', function () {
    [, $business, $subscription] = stripeTrialBusiness();
    $price = configuredStripePrice('pro', BillingInterval::Monthly);
    $subscription = app(SubscriptionLifecycleManager::class)->activate(
        $subscription,
        $price,
        now()->subMonth(),
        now()->addMonth(),
        now()->subMinute(),
        'cus_nested_invoice',
        'sub_nested_invoice',
    );
    $created = now()->timestamp;

    $event = stripeEvent('evt_nested_invoice', 'invoice.payment_succeeded', $created, [
        'id' => 'in_nested_invoice',
        'object' => 'invoice',
        'customer' => 'cus_nested_invoice',
        'status' => 'paid',
        'currency' => 'usd',
        'subtotal' => 10000,
        'total' => 10000,
        'amount_due' => 10000,
        'amount_paid' => 10000,
        'attempt_count' => 1,
        'created' => $created,
        'parent' => [
            'subscription_details' => [
                'subscription' => 'sub_nested_invoice',
                'metadata' => [
                    'application' => 'clipperdesk',
                    'business_public_id' => $business->public_id,
                ],
                'latest_invoice_payment' => ['payment_intent' => 'pi_nested_invoice'],
            ],
        ],
        'status_transitions' => ['paid_at' => $created],
        'lines' => ['data' => [['period' => ['end' => $created + 2592000]]]],
    ]);

    $processed = app(StripeWebhookProcessor::class)->receiveVerified($event);

    expect($processed->status)->toBe('processed')
        ->and(DB::table('billing_invoices')->where('provider_invoice_id', 'in_nested_invoice')->exists())->toBeTrue()
        ->and(DB::table('billing_payments')->where('provider_payment_id', 'pi_nested_invoice')->value('status'))->toBe('succeeded');
});

it('maps Stripe unpaid and paused states to read-only access', function (string $providerStatus) {
    [, $business, $subscription] = stripeTrialBusiness();
    $price = configuredStripePrice('pro', BillingInterval::Monthly);
    $subscription = app(SubscriptionLifecycleManager::class)->activate(
        $subscription,
        $price,
        now()->subMonth(),
        now()->addMonth(),
        now()->subMinute(),
        'cus_restricted',
        'sub_activation',
    );

    app(StripeWebhookProcessor::class)->receiveVerified(
        stripeSubscriptionEvent('evt_'.$providerStatus, now()->timestamp, $providerStatus, $price, $business, false)
    );

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Restricted)
        ->and($subscription->fresh()->restriction_level)->toBe(RestrictionLevel::ReadOnly);
})->with(['unpaid', 'paused']);

it('keeps subscription entitlements separate from member permissions', function () {
    [, $business, $subscription] = stripeTrialBusiness();
    $price = configuredStripePrice('pro', BillingInterval::Monthly);
    app(SubscriptionLifecycleManager::class)->activate($subscription, $price, now(), now()->addMonth(), now(), 'cus_roles', 'sub_roles');

    $staffUser = User::factory()->create();
    $membership = Membership::factory()->create(['business_id' => $business->getKey(), 'user_id' => $staffUser->getKey()]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Receptionist, $staffUser, 'Billing authorization test.');

    expect(app(EntitlementEvaluator::class)->decide($business, 'inventory.enabled', 'read')->allowed)->toBeTrue();
    $this->actingAs($staffUser)->get(route('business.billing.show', $business))->assertForbidden();
});

it('prevents cross-business checkout status and billing access', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    [$otherOwner, $otherBusiness] = stripeTrialBusiness();
    $price = configuredStripePrice('starter', BillingInterval::Annual);
    $attempt = BillingCheckoutAttempt::query()->create([
        'business_id' => $business->getKey(),
        'business_subscription_id' => $subscription->getKey(),
        'billing_plan_price_id' => $price->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'provider' => 'stripe',
        'provider_transaction_id' => 'cs_isolated',
        'status' => 'pending',
    ]);

    $this->actingAs($otherOwner)
        ->postJson(route('business.billing.checkout.status', $business), ['attempt_id' => $attempt->public_id])
        ->assertForbidden();
    $this->actingAs($otherOwner)
        ->postJson(route('business.billing.checkout.status', $otherBusiness), ['attempt_id' => $attempt->public_id])
        ->assertNotFound();
});

it('rejects invalid Stripe signatures and accepts a valid signed event', function () {
    config(['billing.stripe.webhook_secret' => 'whsec_test_clipperdesk']);
    $payload = json_encode(stripeEvent('evt_signature', 'unhandled.test', now()->timestamp, []), JSON_THROW_ON_ERROR);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_clipperdesk');

    $this->call('POST', route('billing.webhooks.stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 'bad',
    ], $payload)->assertBadRequest();
    $this->call('POST', route('billing.webhooks.stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $payload)->assertNoContent();

    expect(BillingProviderEvent::query()->where('provider_event_id', 'evt_signature')->where('signature_verified', true)->exists())->toBeTrue();
});

it('schedules an over-limit downgrade without deleting business resources', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $pro = configuredStripePrice('pro', BillingInterval::Monthly);
    $starter = configuredStripePrice('starter', BillingInterval::Monthly);
    $subscription = app(SubscriptionLifecycleManager::class)->activate($subscription, $pro, now(), now()->addMonth(), now(), 'cus_limit', 'sub_limit');
    foreach (['First Location', 'Second Location'] as $name) {
        $business->locations()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'time_zone' => 'UTC',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    $change = app(SubscriptionLifecycleManager::class)->requestPlanChange(
        $subscription,
        $starter,
        $owner,
        'Reduce plan at renewal without deleting resources.',
        false,
    );

    expect($change->kind)->toBe('over_limit_downgrade')
        ->and($change->effective_at->equalTo($subscription->current_period_ends_at))->toBeTrue()
        ->and($business->locations()->count())->toBe(2)
        ->and($subscription->fresh()->billing_plan_id)->toBe($pro->billing_plan_id);
});

it('opens a Stripe-hosted confirmation for an upgrade and clears a legacy stuck change', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $starter = configuredStripePrice('starter', BillingInterval::Monthly);
    $pro = configuredStripePrice('pro', BillingInterval::Monthly);
    $subscription = app(SubscriptionLifecycleManager::class)->activate($subscription, $starter, now(), now()->addMonth(), now(), 'cus_upgrade', 'sub_upgrade');
    config([
        'billing.stripe.secret' => 'sk_test_local',
        'billing.stripe.webhook_secret' => 'whsec_test_local',
    ]);

    $legacyChange = app(SubscriptionLifecycleManager::class)->requestPlanChange(
        $subscription,
        $pro,
        $owner,
        'Legacy direct update awaiting Stripe.',
        false,
        true,
    );

    $provider = $this->mock(SubscriptionProvider::class, function (MockInterface $mock) use ($subscription, $pro): void {
        $mock->shouldReceive('planChangePortalUrl')->once()
            ->withArgs(fn (BusinessSubscription $candidate, BillingPlanPrice $price, string $returnUrl, string $completedUrl) => $candidate->is($subscription)
                && $price->is($pro)
                && str_contains($returnUrl, 'plan_change=canceled')
                && str_contains($completedUrl, 'plan_change=success'))
            ->andReturn('https://billing.stripe.test/upgrade-session');
        $mock->shouldNotReceive('changePrice');
    });
    app()->instance(SubscriptionProvider::class, $provider);

    $response = $this->actingAs($owner)->postJson(route('business.billing.plan-change', $business), [
        'price_id' => $pro->getKey(),
        'reason' => 'Owner self-service upgrade.',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'requires_confirmation')
        ->assertJsonPath('url', 'https://billing.stripe.test/upgrade-session');
    expect($legacyChange->fresh()->superseded_at)->not->toBeNull()
        ->and($subscription->fresh()->billing_plan_price_id)->toBe($starter->getKey())
        ->and($subscription->changes()->whereNull('applied_at')->whereNull('superseded_at')->count())->toBe(0);
});

it('allows an interval switch through Stripe but does not offer self-service plan downgrades', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $starterMonthly = configuredStripePrice('starter', BillingInterval::Monthly);
    $starterAnnual = configuredStripePrice('starter', BillingInterval::Annual);
    $proMonthly = configuredStripePrice('pro', BillingInterval::Monthly);
    $subscription = app(SubscriptionLifecycleManager::class)->activate($subscription, $starterMonthly, now(), now()->addMonth(), now(), 'cus_interval', 'sub_interval');
    config(['billing.stripe.secret' => 'sk_test_local', 'billing.stripe.webhook_secret' => 'whsec_test_local']);

    $provider = $this->mock(SubscriptionProvider::class, function (MockInterface $mock) use ($starterAnnual): void {
        $mock->shouldReceive('planChangePortalUrl')->once()
            ->withArgs(fn (BusinessSubscription $subscription, BillingPlanPrice $price): bool => $price->is($starterAnnual))
            ->andReturn('https://billing.stripe.test/interval-session');
    });
    app()->instance(SubscriptionProvider::class, $provider);

    $this->actingAs($owner)->postJson(route('business.billing.plan-change', $business), [
        'price_id' => $starterAnnual->getKey(),
        'reason' => 'Switch to annual billing.',
    ])->assertOk()->assertJsonPath('url', 'https://billing.stripe.test/interval-session');

    app(SubscriptionLifecycleManager::class)->activate($subscription->fresh(), $proMonthly, now(), now()->addMonth(), now()->addSecond(), 'cus_interval', 'sub_interval');

    $provider = $this->mock(SubscriptionProvider::class, fn (MockInterface $mock) => $mock->shouldNotReceive('planChangePortalUrl'));
    app()->instance(SubscriptionProvider::class, $provider);

    $this->actingAs($owner)->postJson(route('business.billing.plan-change', $business), [
        'price_id' => $starterMonthly->getKey(),
        'reason' => 'Try to downgrade.',
    ])->assertUnprocessable()->assertJsonPath('message', 'Plan downgrades are not available through self-service. Your current subscription is unchanged.');
});

it('reports a Stripe-confirmed plan change without waiting on a browser redirect', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $starter = configuredStripePrice('starter', BillingInterval::Monthly);
    $pro = configuredStripePrice('pro', BillingInterval::Annual);
    $subscription = app(SubscriptionLifecycleManager::class)->activate($subscription, $starter, now(), now()->addMonth(), now(), 'cus_status', 'sub_status');
    app(SubscriptionLifecycleManager::class)->activate($subscription, $pro, now(), now()->addYear(), now()->addSecond(), 'cus_status', 'sub_status');

    $this->actingAs($owner)->postJson(route('business.billing.plan-change.status', $business), [
        'price_id' => $pro->getKey(),
    ])->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('plan_name', 'Pro')
        ->assertJsonPath('billing_interval', 'annual');
});

it('clears a legacy direct-update record when Stripe expires its pending update', function () {
    [$owner, $business, $subscription] = stripeTrialBusiness();
    $starter = configuredStripePrice('starter', BillingInterval::Monthly);
    $pro = configuredStripePrice('pro', BillingInterval::Monthly);
    $subscription = app(SubscriptionLifecycleManager::class)->activate($subscription, $starter, now(), now()->addMonth(), now(), 'cus_expired_update', 'sub_expired_update');
    $change = app(SubscriptionLifecycleManager::class)->requestPlanChange(
        $subscription,
        $pro,
        $owner,
        'Legacy pending update.',
        false,
        true,
    );
    $occurredAt = now()->addMinute();

    app(StripeWebhookProcessor::class)->receiveVerified(stripeEvent(
        'evt_pending_update_expired',
        'customer.subscription.pending_update_expired',
        $occurredAt->timestamp,
        [
            'id' => 'sub_expired_update',
            'object' => 'subscription',
            'customer' => 'cus_expired_update',
            'metadata' => ['application' => 'clipperdesk', 'business_public_id' => $business->public_id],
        ],
    ));

    expect($change->fresh()->superseded_at?->timestamp)->toBe($occurredAt->timestamp);
});

/** @return array{0: User, 1: Business, 2: BusinessSubscription} */
function stripeTrialBusiness(): array
{
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $trial = BillingPlan::query()->where('code', 'trial')->firstOrFail();
    $subscription = BusinessSubscription::query()->create([
        'business_id' => $business->getKey(),
        'billing_plan_id' => $trial->getKey(),
        'provider' => 'stripe',
        'status' => SubscriptionStatus::Trialing,
        'restriction_level' => RestrictionLevel::None,
        'trial_started_at' => now(),
        'trial_ends_at' => now()->addDays(14),
    ]);

    return [$owner, $business, $subscription];
}

function configuredStripePrice(string $planCode, BillingInterval $interval): BillingPlanPrice
{
    $priceId = "price_{$planCode}_{$interval->value}_test";
    $amount = (int) data_get(config("billing.plans.{$planCode}"), "prices.{$interval->value}.amount_minor");
    config([
        "billing.plans.{$planCode}.prices.{$interval->value}.price_id" => $priceId,
    ]);

    return BillingPlanPrice::query()->firstOrCreate(
        ['provider_price_id' => $priceId],
        [
            'billing_plan_id' => BillingPlan::query()->where('code', $planCode)->value('id'),
            'billing_interval' => $interval,
            'currency' => 'USD',
            'amount_minor' => $amount,
            'provider' => 'stripe',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
        ],
    );
}

/** @param array<string, mixed> $object */
function stripeEvent(string $id, string $type, int $created, array $object): array
{
    return ['id' => $id, 'type' => $type, 'created' => $created, 'data' => ['object' => $object]];
}

function stripeSubscriptionEvent(string $id, int $created, string $status, BillingPlanPrice $price, Business $business, bool $cancelAtPeriodEnd, ?BillingCheckoutAttempt $attempt = null): array
{
    $metadata = [
        'application' => 'clipperdesk',
        'business_public_id' => $business->public_id,
    ];
    if ($attempt) {
        $metadata['billing_checkout_attempt_id'] = $attempt->public_id;
        $metadata['plan_price_id'] = (string) $price->getKey();
    }

    return stripeEvent($id, 'customer.subscription.updated', $created, [
        'id' => 'sub_activation',
        'object' => 'subscription',
        'customer' => 'cus_activation',
        'status' => $status,
        'cancel_at_period_end' => $cancelAtPeriodEnd,
        'cancel_at' => $cancelAtPeriodEnd ? $created + 2592000 : null,
        'metadata' => $metadata,
        'items' => ['data' => [[
            'price' => ['id' => $price->provider_price_id],
            'current_period_start' => $created,
            'current_period_end' => $created + 2592000,
        ]]],
    ]);
}

function stripeInvoiceEvent(string $id, string $type, int $created, string $subscriptionId, int $attemptCount): array
{
    return stripeEvent($id, $type, $created, [
        'id' => 'in_'.$id,
        'object' => 'invoice',
        'subscription' => $subscriptionId,
        'customer' => 'cus_dunning',
        'status' => $type === 'invoice.paid' ? 'paid' : 'open',
        'currency' => 'usd',
        'subtotal' => 10000,
        'total' => 10000,
        'amount_due' => 10000,
        'amount_paid' => $type === 'invoice.paid' ? 10000 : 0,
        'attempt_count' => $attemptCount,
        'payment_intent' => 'pi_'.$id,
        'created' => $created,
        'status_transitions' => ['paid_at' => $type === 'invoice.paid' ? $created : null],
        'lines' => ['data' => [['period' => ['end' => $created + 2592000]]]],
    ]);
}
