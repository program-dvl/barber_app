<?php

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingCoupon;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\EntitlementDefinition;
use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\Billing\Providers\PaddleSubscriptionProvider;
use App\Domain\Billing\Services\EntitlementCatalogManager;
use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\OwnerOnboardingService;
use App\Domain\Billing\Services\PaddleWebhookProcessor;
use App\Domain\Billing\Services\PaddleWebhookSignatureVerifier;
use App\Domain\Billing\Services\StripeWebhookProcessor;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\LemonSqueezyOrders\LemonSqueezyOrderResource;
use App\Filament\Resources\LemonSqueezySubscriptions\LemonSqueezySubscriptionResource;
use App\Filament\Resources\Prices\PriceResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\StripeOrders\StripeOrderResource;
use App\Filament\Resources\StripeSubscriptions\StripeSubscriptionResource;
use App\Models\User;
use App\Notifications\BillingLifecycleNotice;
use App\Support\Imports\ImportEntitlementGuard;
use App\Support\Jobs\EnsureJobEntitlement;
use App\Support\Jobs\EntitlementAwareJob;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('verifies Paddle webhook signatures with replay-window protection', function () {
    config(['billing.paddle.webhook_secret' => 'paddle-test-secret']);
    $payload = '{"event_id":"evt_test","event_type":"subscription.created"}';
    $timestamp = (string) time();
    $signature = 'ts='.$timestamp.';h1='.hash_hmac('sha256', $timestamp.':'.$payload, 'paddle-test-secret');

    expect(app(PaddleWebhookSignatureVerifier::class)->verify($payload, $signature))->toBeTrue()
        ->and(app(PaddleWebhookSignatureVerifier::class)->verify($payload, 'ts='.$timestamp.';h1=wrong'))->toBeFalse();
});

it('publishes the approved Paddle Starter and Pro catalog with monthly and annual prices', function () {
    $starter = BillingPlan::query()->where('code', 'starter')->with('prices')->firstOrFail();
    $pro = BillingPlan::query()->where('code', 'pro')->with('prices')->firstOrFail();

    expect($starter->name)->toBe('Starter')
        ->and($starter->prices->pluck('provider_price_id')->all())->toContain(
            'pri_01m01trgvhqc0mn20q9cv9agpv',
            'pri_01m01twkb2j0b1kchq9xpygsn6',
        )
        ->and($starter->prices->firstWhere('billing_interval', BillingInterval::Monthly)->amount_minor)->toBe(5000)
        ->and($pro->name)->toBe('Pro')
        ->and($pro->prices->pluck('provider_price_id')->all())->toContain(
            'pri_01m01tsht5zh8hqfbwgphna6a9',
            'pri_01m01twz5q1qvq0w8rmjwnaakq',
        )
        ->and($pro->prices->firstWhere('billing_interval', BillingInterval::Annual)->amount_minor)->toBe(100000);
});

it('fails safely without calling Paddle when the local API key is still a placeholder', function () {
    [, $business] = createTrialBusiness();
    $price = BillingPlanPrice::query()->where('provider', 'paddle')->firstOrFail();
    config(['billing.paddle.api_key' => 'your-paddle-api-key']);
    Http::preventStrayRequests();

    try {
        app(PaddleSubscriptionProvider::class)->createCheckout($business, $price, 'https://example.test/success', 'https://example.test/canceled');
        $this->fail('Expected the local Paddle placeholder to prevent checkout.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(503)
            ->and($exception->getMessage())->toContain('not configured');
    }
});

it('conceals a Paddle provider rejection behind a safe checkout response', function () {
    [, $business] = createTrialBusiness();
    $price = BillingPlanPrice::query()->where('provider', 'paddle')->firstOrFail();
    config(['billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    Http::fake([
        'https://sandbox-api.paddle.com/prices/*' => Http::response(['data' => ['id' => $price->provider_price_id]]),
        'https://sandbox-api.paddle.com/customers' => Http::response([
            'error' => ['detail' => 'Authentication header included, but incorrectly formatted.'],
        ], 403),
    ]);

    try {
        app(PaddleSubscriptionProvider::class)->createCheckout($business, $price, 'https://example.test/success', 'https://example.test/canceled');
        $this->fail('Expected the provider rejection to prevent checkout.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(503)
            ->and($exception->getMessage())->toBe('Secure checkout is temporarily unavailable. Please try again shortly.');
    }
});

it('renders a tenant scoped inline Paddle checkout review page', function () {
    [$owner, $business] = createTrialBusiness();
    $price = BillingPlanPrice::query()->where('provider', 'paddle')->firstOrFail();
    config([
        'billing.provider' => 'paddle',
        'billing.paddle.client_side_token' => 'test_good_hours_checkout',
        'billing.paddle.sandbox' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('business.billing.checkout.form', [$business, 'price_id' => $price->getKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Checkout')
            ->where('businessLabel', $business->name)
            ->where('price.id', $price->getKey())
            ->where('paddle.environment', 'sandbox')
            ->where('paddle.configured', true)
            ->missing('paddle.client_side_token'));
});

it('shows only an allow-listed checkout result on the billing overview', function () {
    [$owner, $business] = createTrialBusiness();

    $this->actingAs($owner)
        ->get(route('business.billing.show', $business).'?checkout=success')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Overview')
            ->where('checkoutStatus', 'success'));

    $this->actingAs($owner)
        ->get(route('business.billing.show', $business).'?checkout=forged-paid-state')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Overview')
            ->where('checkoutStatus', null));
});

it('creates an inline Paddle transaction without returning an external checkout redirect', function () {
    [$owner, $business] = createTrialBusiness();
    $price = BillingPlanPrice::query()->where('provider', 'paddle')->firstOrFail();
    config([
        'billing.provider' => 'paddle',
        'billing.paddle.api_key' => 'pdl_sdbx_apikey_good_hours',
        'billing.paddle.client_side_token' => 'test_good_hours_checkout',
        'billing.paddle.sandbox' => true,
    ]);
    Http::fake([
        'https://sandbox-api.paddle.com/prices/*' => Http::response(['data' => ['id' => $price->provider_price_id]]),
        'https://sandbox-api.paddle.com/customers' => Http::response(['data' => ['id' => 'ctm_good_hours']]),
        'https://sandbox-api.paddle.com/transactions' => Http::response(['data' => [
            'id' => 'txn_good_hours',
            'checkout' => ['url' => 'https://sandbox-pay.paddle.io/external'],
        ]]),
    ]);

    $this->actingAs($owner)
        ->postJson(route('business.billing.checkout', $business), ['price_id' => $price->getKey()])
        ->assertCreated()
        ->assertJsonPath('transaction_id', 'txn_good_hours')
        ->assertJsonPath('environment', 'sandbox')
        ->assertJsonPath('client_side_token', 'test_good_hours_checkout')
        ->assertJsonMissingPath('url');

    Http::assertSent(fn ($request) => $request->url() === 'https://sandbox-api.paddle.com/transactions'
        && $request['custom_data']['application'] === 'good_hours'
        && $request['custom_data']['business_public_id'] === $business->public_id);
    expect(BillingCheckoutAttempt::query()->where('business_id', $business->getKey())
        ->where('provider_transaction_id', 'txn_good_hours')->where('status', 'pending')->exists())->toBeTrue();
});

it('recovers a completed Paddle checkout on refresh and records the active plan invoice and payment', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    $pro = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'pro'))
        ->where('provider', 'paddle')
        ->where('billing_interval', BillingInterval::Monthly)
        ->latest('id')
        ->firstOrFail();
    $subscription->update(['provider_customer_id' => 'ctm_recovery']);
    config(['billing.provider' => 'paddle', 'billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    $transaction = [
        'id' => 'txn_recovery',
        'status' => 'completed',
        'customer_id' => 'ctm_recovery',
        'subscription_id' => 'sub_recovery',
        'currency_code' => 'USD',
        'custom_data' => [
            'application' => 'good_hours',
            'business_public_id' => $business->public_id,
            'plan_price_id' => (string) $pro->getKey(),
        ],
        'items' => [['price' => ['id' => $pro->provider_price_id], 'quantity' => 1]],
        'details' => ['totals' => ['subtotal' => 10000, 'discount' => 0, 'tax' => 1500, 'total' => 11500]],
        'payments' => [[
            'payment_method_id' => 'paymtd_recovery',
            'method_details' => ['type' => 'card', 'card' => ['type' => 'visa', 'last4' => '4242']],
        ]],
        'invoice_number' => '72804-TEST',
        'billed_at' => now()->toIso8601String(),
    ];
    $providerSubscription = [
        'id' => 'sub_recovery',
        'status' => 'active',
        'customer_id' => 'ctm_recovery',
        'custom_data' => ['application' => 'good_hours', 'business_public_id' => $business->public_id],
        'items' => [['price' => ['id' => $pro->provider_price_id], 'quantity' => 1]],
        'current_billing_period' => [
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addMonth()->toIso8601String(),
        ],
    ];
    Http::fake([
        'https://sandbox-api.paddle.com/transactions/txn_recovery' => Http::response(['data' => $transaction]),
        'https://sandbox-api.paddle.com/subscriptions/sub_recovery' => Http::response(['data' => $providerSubscription]),
        'https://sandbox-api.paddle.com/transactions*' => Http::response(['data' => [$transaction]]),
    ]);

    $this->actingAs($owner)
        ->get(route('business.billing.show', $business))
        ->assertInertia(fn (Assert $page) => $page->where('checkoutRecovery', true));

    $this->actingAs($owner)
        ->postJson(route('business.billing.checkout.confirm', $business), [])
        ->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('plan_name', 'Pro')
        ->assertJsonPath('invoice_count', 1);

    $paid = $business->subscription()->firstOrFail();
    expect($paid->status)->toBe(SubscriptionStatus::Active)
        ->and($paid->billing_plan_id)->toBe($pro->billing_plan_id)
        ->and($paid->provider_subscription_id)->toBe('sub_recovery')
        ->and($paid->payment_method_type)->toBe('visa')
        ->and($paid->payment_method_last_four)->toBe('4242')
        ->and($paid->invoices()->where('provider_invoice_id', 'txn_recovery')->where('total_minor', 11500)->exists())->toBeTrue()
        ->and(DB::table('billing_payments')->where('provider_payment_id', 'txn_recovery')->where('status', 'succeeded')->exists())->toBeTrue()
        ->and(BillingCheckoutAttempt::query()->where('provider_transaction_id', 'txn_recovery')->where('status', 'confirmed')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->postJson(route('business.billing.checkout.confirm', $business), ['transaction_id' => 'txn_recovery'])
        ->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('invoice_count', 1);

    app(PaddleWebhookProcessor::class)->receiveVerified([
        'event_id' => 'evt_after_api_recovery',
        'event_type' => 'transaction.completed',
        'occurred_at' => now()->toIso8601String(),
        'data' => $transaction,
    ]);
    expect($paid->invoices()->count())->toBe(1)
        ->and(DB::table('billing_payments')->where('business_id', $business->getKey())->count())->toBe(1);
});

it('keeps a pending Paddle checkout on trial without creating financial records', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    $pro = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'pro'))
        ->where('provider', 'paddle')
        ->where('billing_interval', BillingInterval::Monthly)
        ->latest('id')
        ->firstOrFail();
    $subscription->update(['provider_customer_id' => 'ctm_pending']);
    BillingCheckoutAttempt::query()->create([
        'business_id' => $business->getKey(),
        'business_subscription_id' => $subscription->getKey(),
        'billing_plan_price_id' => $pro->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'provider' => 'paddle',
        'provider_transaction_id' => 'txn_pending',
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);
    config(['billing.provider' => 'paddle', 'billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    Http::fake([
        'https://sandbox-api.paddle.com/transactions/txn_pending' => Http::response(['data' => [
            'id' => 'txn_pending',
            'status' => 'ready',
        ]]),
    ]);

    $this->actingAs($owner)
        ->postJson(route('business.billing.checkout.confirm', $business), ['transaction_id' => 'txn_pending'])
        ->assertOk()
        ->assertJsonPath('status', 'pending');

    expect($business->subscription()->firstOrFail()->status)->toBe(SubscriptionStatus::Trialing)
        ->and(BillingInvoice::query()->where('business_id', $business->getKey())->exists())->toBeFalse()
        ->and(BillingCheckoutAttempt::query()->where('provider_transaction_id', 'txn_pending')->value('status'))->toBe('pending');
});

it('prevents cross tenant checkout confirmation before contacting Paddle', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    [$otherOwner, $otherBusiness] = createTrialBusiness();
    $pro = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'pro'))
        ->where('provider', 'paddle')
        ->where('billing_interval', BillingInterval::Monthly)
        ->latest('id')
        ->firstOrFail();
    BillingCheckoutAttempt::query()->create([
        'business_id' => $business->getKey(),
        'business_subscription_id' => $subscription->getKey(),
        'billing_plan_price_id' => $pro->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'provider' => 'paddle',
        'provider_transaction_id' => 'txn_tenanta',
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);
    config(['billing.provider' => 'paddle', 'billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    Http::preventStrayRequests();

    $this->actingAs($otherOwner)
        ->postJson(route('business.billing.checkout.confirm', $otherBusiness), ['transaction_id' => 'txn_tenanta'])
        ->assertNotFound();
    $this->actingAs($otherOwner)
        ->postJson(route('business.billing.checkout.confirm', $business), ['transaction_id' => 'txn_tenanta'])
        ->assertForbidden();

    Http::assertNothingSent();
});

it('applies a Paddle upgrade immediately with proration and exposes the paid plan in shared navigation', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    $starter = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'starter'))
        ->where('billing_interval', BillingInterval::Monthly)->firstOrFail();
    $pro = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'pro'))
        ->where('billing_interval', BillingInterval::Monthly)->firstOrFail();
    config(['billing.provider' => 'paddle', 'billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    app(SubscriptionLifecycleManager::class)->activate($subscription, $starter, now(), now()->addMonth(), now(), 'ctm_standard', 'sub_standard');
    Http::fake([
        'https://sandbox-api.paddle.com/subscriptions/sub_standard' => Http::response(['data' => [
            'id' => 'sub_standard',
            'items' => [['price' => ['id' => $starter->provider_price_id], 'quantity' => 1]],
        ]]),
    ]);

    $this->actingAs($owner)
        ->postJson(route('business.billing.plan-change', $business), [
            'price_id' => $pro->getKey(),
            'timing' => 'immediate',
            'reason' => 'Owner self-service upgrade.',
        ])
        ->assertAccepted();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request->url() === 'https://sandbox-api.paddle.com/subscriptions/sub_standard'
        && $request['items'][0]['price_id'] === $pro->provider_price_id
        && $request['proration_billing_mode'] === 'prorated_immediately');
    expect($business->subscription()->firstOrFail()->billing_plan_id)->toBe($pro->billing_plan_id);

    $this->actingAs($owner)->get(route('business.billing.show', $business))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.subscription.plan_name', 'Pro')
            ->where('tenant.subscription.status', 'active'));
});

it('fails a Paddle plan change safely without changing local access', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    $starter = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'starter'))
        ->where('billing_interval', BillingInterval::Monthly)->firstOrFail();
    $pro = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'pro'))
        ->where('billing_interval', BillingInterval::Monthly)->firstOrFail();
    config(['billing.provider' => 'paddle', 'billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    app(SubscriptionLifecycleManager::class)->activate($subscription, $starter, now(), now()->addMonth(), now(), 'ctm_standard', 'sub_standard');
    Http::fake([
        'https://sandbox-api.paddle.com/subscriptions/sub_standard' => Http::sequence()
            ->push(['data' => [
                'id' => 'sub_standard',
                'items' => [['price' => ['id' => $starter->provider_price_id], 'quantity' => 1]],
            ]])
            ->push(['error' => ['detail' => 'Internal provider diagnostic that must not reach the customer.']], 500),
    ]);

    $this->actingAs($owner)
        ->postJson(route('business.billing.plan-change', $business), [
            'price_id' => $pro->getKey(),
            'timing' => 'immediate',
            'reason' => 'Owner self-service upgrade.',
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'The billing service is temporarily unavailable. No subscription change was made. Please try again shortly.');

    expect($business->subscription()->firstOrFail()->billing_plan_id)->toBe($starter->billing_plan_id)
        ->and($business->subscription()->firstOrFail()->changes()->exists())->toBeFalse();
});

it('does not call Paddle when reactivation is not valid for the current status', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    $starter = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'starter'))
        ->where('billing_interval', BillingInterval::Monthly)->firstOrFail();
    config(['billing.provider' => 'paddle', 'billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    app(SubscriptionLifecycleManager::class)->activate($subscription, $starter, now(), now()->addMonth(), now(), 'ctm_standard', 'sub_standard');
    Http::preventStrayRequests();

    $this->actingAs($owner)
        ->postJson(route('business.billing.reactivate', $business), ['reason' => 'Invalid duplicate request.'])
        ->assertConflict()
        ->assertJsonPath('message', 'Only a scheduled cancellation can be reactivated.');

    Http::assertNothingSent();
});

it('schedules a same-interval Paddle downgrade and removes a scheduled cancellation correctly', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    $starter = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'starter'))
        ->where('billing_interval', BillingInterval::Monthly)->firstOrFail();
    $pro = BillingPlanPrice::query()->whereHas('plan', fn ($query) => $query->where('code', 'pro'))
        ->where('billing_interval', BillingInterval::Monthly)->firstOrFail();
    config(['billing.provider' => 'paddle', 'billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    $active = app(SubscriptionLifecycleManager::class)->activate($subscription, $pro, now(), now()->addMonth(), now(), 'ctm_standard', 'sub_standard');
    Http::fake([
        'https://sandbox-api.paddle.com/subscriptions/sub_standard' => Http::response(['data' => [
            'id' => 'sub_standard',
            'items' => [['price' => ['id' => $pro->provider_price_id], 'quantity' => 1]],
        ]]),
        'https://sandbox-api.paddle.com/subscriptions/sub_standard/cancel' => Http::response(['data' => [
            'id' => 'sub_standard',
            'status' => 'active',
            'scheduled_change' => ['action' => 'cancel', 'effective_at' => now()->addMonth()->toIso8601String()],
        ]]),
    ]);

    $this->actingAs($owner)
        ->postJson(route('business.billing.plan-change', $business), [
            'price_id' => $starter->getKey(),
            'timing' => 'period_end',
            'reason' => 'Owner self-service downgrade.',
        ])
        ->assertAccepted();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['items'][0]['price_id'] === $starter->provider_price_id
        && $request['proration_billing_mode'] === 'prorated_next_billing_period');
    expect($business->subscription()->firstOrFail()->billing_plan_id)->toBe($pro->billing_plan_id)
        ->and($business->subscription()->firstOrFail()->changes()->whereNull('applied_at')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->postJson(route('business.billing.cancel', $business), ['reason' => 'Owner requested cancellation.'])
        ->assertOk();
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://sandbox-api.paddle.com/subscriptions/sub_standard/cancel'
        && $request['effective_from'] === 'next_billing_period');
    $scheduled = $active->fresh();
    $this->actingAs($owner)
        ->postJson(route('business.billing.reactivate', $business), ['reason' => 'Owner chose to stay.'])
        ->assertOk();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request->url() === 'https://sandbox-api.paddle.com/subscriptions/sub_standard'
        && array_key_exists('scheduled_change', $request->data())
        && $request['scheduled_change'] === null);
    expect($scheduled->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('discards signed Paddle events belonging to another SaaS in the shared account', function () {
    config(['billing.paddle.webhook_secret' => 'paddle-shared-account-secret']);
    $payload = json_encode([
        'event_id' => 'evt_other_saas',
        'event_type' => 'transaction.completed',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'id' => 'txn_other_saas',
            'custom_data' => ['application' => 'other_saas'],
            'customer' => ['email' => 'private@other-saas.example'],
        ],
    ], JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = 'ts='.$timestamp.';h1='.hash_hmac('sha256', $timestamp.':'.$payload, 'paddle-shared-account-secret');

    $this->call('POST', route('billing.webhooks.paddle'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PADDLE_SIGNATURE' => $signature,
    ], $payload)->assertNoContent();

    expect(BillingProviderEvent::query()->where('provider_event_id', 'evt_other_saas')->exists())->toBeFalse();
});

function createBillingPlan(string $code, array $entitlements, int $monthly = 2900, int $annual = 29000): array
{
    $plan = BillingPlan::query()->create([
        'code' => $code,
        'name' => str($code)->headline(),
        'description' => "{$code} test plan",
        'is_active' => true,
        'available_from' => now()->subDay(),
    ]);

    foreach ($entitlements as $key => $value) {
        $definition = EntitlementDefinition::query()->where('key', $key)->firstOrFail();
        $plan->entitlements()->create([
            'entitlement_definition_id' => $definition->getKey(),
            'value' => $value,
            'effective_from' => now()->subDay(),
            'change_reason' => 'Test catalog.',
        ]);
    }

    $monthlyPrice = BillingPlanPrice::query()->create([
        'billing_plan_id' => $plan->getKey(), 'billing_interval' => BillingInterval::Monthly,
        'currency' => 'USD', 'amount_minor' => $monthly, 'provider' => 'stripe',
        'provider_price_id' => "price_{$code}_monthly", 'is_active' => true, 'effective_from' => now()->subDay(),
    ]);
    $annualPrice = BillingPlanPrice::query()->create([
        'billing_plan_id' => $plan->getKey(), 'billing_interval' => BillingInterval::Annual,
        'currency' => 'USD', 'amount_minor' => $annual, 'provider' => 'stripe',
        'provider_price_id' => "price_{$code}_annual", 'is_active' => true, 'effective_from' => now()->subDay(),
    ]);

    return [$plan, $monthlyPrice, $annualPrice];
}

function createTrialBusiness(): array
{
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $trial = BillingPlan::query()->where('code', 'trial')->firstOrFail();
    $subscription = BusinessSubscription::query()->create([
        'business_id' => $business->getKey(), 'billing_plan_id' => $trial->getKey(),
        'provider' => 'stripe', 'status' => SubscriptionStatus::Trialing,
        'restriction_level' => RestrictionLevel::None,
        'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14),
    ]);

    return [$owner, $business, $subscription];
}

it('creates a verified owner tenant and dated trial exactly once', function () {
    $user = User::factory()->unverified()->create();
    OwnerRegistrationIntent::query()->create(['user_id' => $user->getKey(), 'business_name' => 'Exactly Once Salon', 'status' => 'pending']);
    $user->forceFill(['email_verified_at' => now()])->save();

    event(new Verified($user));
    event(new Verified($user));

    $intent = OwnerRegistrationIntent::query()->where('user_id', $user->getKey())->firstOrFail();
    $subscription = BusinessSubscription::query()->where('business_id', $intent->business_id)->firstOrFail();
    expect(Business::query()->count())->toBe(1)
        ->and($user->memberships()->count())->toBe(1)
        ->and(BusinessSubscription::query()->count())->toBe(1)
        ->and($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_started_at)->not->toBeNull()
        ->and($subscription->trial_ends_at->greaterThan($subscription->trial_started_at))->toBeTrue();
    $this->assertDatabaseCount('instrumentation_events', 1);
    $this->assertDatabaseHas('instrumentation_events', ['business_id' => $intent->business_id, 'event_name' => 'trial.qualified_started']);
});

it('refuses owner onboarding until email verification', function () {
    $user = User::factory()->unverified()->create();
    OwnerRegistrationIntent::query()->create(['user_id' => $user->getKey(), 'business_name' => 'Unverified Salon', 'status' => 'pending']);

    expect(fn () => app(OwnerOnboardingService::class)->complete($user))->toThrow(LogicException::class)
        ->and(Business::query()->count())->toBe(0);
});

it('supports trial conversion with monthly and annual prices plus saved payment evidence', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    [, $monthly, $annual] = createBillingPlan('core', ['locations.max' => 1, 'staff.max' => 5, 'exports.enabled' => true, 'billing.manage' => true]);
    $lifecycle = app(SubscriptionLifecycleManager::class);

    $paid = $lifecycle->activate($subscription, $monthly, now(), now()->addMonth(), now(), 'cus_goodhours', 'sub_goodhours', 'visa', '4242');
    expect($paid->status)->toBe(SubscriptionStatus::Active)
        ->and($paid->billing_interval)->toBe(BillingInterval::Monthly)
        ->and($paid->payment_method_type)->toBe('visa')
        ->and($paid->payment_method_last_four)->toBe('4242');

    $change = $lifecycle->requestPlanChange($paid, $annual, $owner, 'Switch to annual billing.', false);
    expect($change->applied_at)->not->toBeNull()
        ->and($business->subscription()->first()->billing_interval)->toBe(BillingInterval::Annual);
});

it('schedules cancellation at period end and supports reactivation and reasoned support cancellation', function () {
    [$owner, , $subscription] = createTrialBusiness();
    [, $price] = createBillingPlan('cancelable', ['locations.max' => 1, 'staff.max' => 5, 'exports.enabled' => true, 'billing.manage' => true]);
    $lifecycle = app(SubscriptionLifecycleManager::class);
    $active = $lifecycle->activate($subscription, $price, now(), now()->addMonth(), now());

    $scheduled = $lifecycle->scheduleCancellation($active, $owner, 'Closing after this cycle.');
    expect($scheduled->status)->toBe(SubscriptionStatus::CancelScheduled)
        ->and($scheduled->cancel_at->equalTo($scheduled->current_period_ends_at))->toBeTrue();

    $reactivated = $lifecycle->reactivate($scheduled, $owner, 'Business is staying.');
    expect($reactivated->status)->toBe(SubscriptionStatus::Active)->and($reactivated->cancel_at)->toBeNull();

    $canceled = $lifecycle->supportCancel($reactivated, $owner, 'Documented support request GH-42.');
    expect($canceled->status)->toBe(SubscriptionStatus::Canceled)
        ->and($canceled->restriction_level)->toBe(RestrictionLevel::ReadOnly)
        ->and($canceled->exportIsAvailable())->toBeTrue();
});

it('progresses renewal failures through retry grace restriction and safe recovery', function () {
    [, $business, $subscription] = createTrialBusiness();
    [, $price] = createBillingPlan('recovery', ['locations.max' => 1, 'staff.max' => 5, 'inventory.enabled' => true, 'exports.enabled' => true, 'billing.manage' => true]);
    $lifecycle = app(SubscriptionLifecycleManager::class);
    $active = $lifecycle->activate($subscription, $price, now(), now()->addMonth(), now());
    $failed = $lifecycle->renewalFailed($active, 1, now()->addHour());
    $grace = $lifecycle->renewalFailed($failed, 2, now()->addHours(2));

    expect($failed->status)->toBe(SubscriptionStatus::PastDue)
        ->and($grace->status)->toBe(SubscriptionStatus::Grace)
        ->and(DB::table('billing_notices')->count())->toBe(2)
        ->and(app(EntitlementEvaluator::class)->decide($business, 'inventory.enabled', 'use')->allowed)->toBeTrue();

    $lifecycle->advanceDunning($grace->grace_ends_at->addSecond());
    $restricted = $business->subscription()->firstOrFail();
    expect($restricted->status)->toBe(SubscriptionStatus::Restricted)
        ->and(app(EntitlementEvaluator::class)->decide($business, 'inventory.enabled', 'use')->allowed)->toBeFalse()
        ->and(app(EntitlementEvaluator::class)->decide($business, 'exports.enabled', 'export')->allowed)->toBeTrue();

    $recovered = $lifecycle->recover($restricted, now()->addMonth(), now()->addDays(8));
    expect($recovered->status)->toBe(SubscriptionStatus::Active)
        ->and($recovered->restriction_level)->toBe(RestrictionLevel::None);
});

it('schedules an over-limit downgrade without deleting usage and blocks only further consumption after it applies', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    [, $largePrice] = createBillingPlan('large', ['locations.max' => 5, 'staff.max' => 10, 'exports.enabled' => true, 'billing.manage' => true]);
    [, $smallPrice] = createBillingPlan('small', ['locations.max' => 1, 'staff.max' => 2, 'exports.enabled' => true, 'billing.manage' => true], 1000, 10000);
    $lifecycle = app(SubscriptionLifecycleManager::class);
    $active = $lifecycle->activate($subscription, $largePrice, now()->subMonth(), now()->addDay(), now());
    Location::factory()->count(2)->create(['business_id' => $business->getKey()]);
    StaffProfile::factory()->count(3)->create(['business_id' => $business->getKey()]);

    $change = $lifecycle->requestPlanChange($active, $smallPrice, $owner, 'Reduce subscription.', false);
    expect($change->kind)->toBe('over_limit_downgrade')
        ->and($change->effective_at->equalTo($active->current_period_ends_at))->toBeTrue()
        ->and(Location::query()->where('business_id', $business->getKey())->count())->toBe(2)
        ->and(StaffProfile::query()->where('business_id', $business->getKey())->count())->toBe(3);

    $lifecycle->applyDuePlanChanges($active->current_period_ends_at->addSecond());
    $decision = app(EntitlementEvaluator::class)->decide($business->fresh(), 'staff.max', 'create', 1);
    expect($decision->allowed)->toBeFalse()->and($decision->code)->toBe('limit_exceeded')
        ->and(app(EntitlementEvaluator::class)->decide($business->fresh(), 'staff.max', 'read')->allowed)->toBeTrue();
});

it('enforces entitlements in direct checks jobs and imports', function () {
    [, $business] = createTrialBusiness();
    $job = new class($business->getKey()) implements EntitlementAwareJob
    {
        public function __construct(private readonly int $id) {}

        public function businessId(): int
        {
            return $this->id;
        }

        public function entitlementKey(): string
        {
            return 'inventory.enabled';
        }

        public function entitlementOperation(): string
        {
            return 'job';
        }

        public function entitlementIncrease(): int
        {
            return 0;
        }
    };

    expect(fn () => app(EnsureJobEntitlement::class)->handle($job, fn () => true))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ImportEntitlementGuard::class)->authorize($business, 'staff.max', 3))->toThrow(AuthorizationException::class);
});

it('keeps effective dated entitlement history with actor and audit evidence', function () {
    [$owner] = createTrialBusiness();
    $plan = BillingPlan::query()->where('code', 'trial')->firstOrFail();
    $manager = app(EntitlementCatalogManager::class);
    $first = $manager->changePlanEntitlement($plan, 'staff.max', 4, now()->addDay(), $owner, 'Approved launch adjustment.');
    $second = $manager->changePlanEntitlement($plan, 'staff.max', 6, now()->addDays(30), $owner, 'Approved growth adjustment.');

    expect($first->fresh()->effective_until->equalTo($second->effective_from))->toBeTrue()
        ->and($second->changed_by_user_id)->toBe($owner->getKey())
        ->and(DB::table('audit_events')->where('action', 'billing.plan_entitlement.changed')->count())->toBe(2);
});

it('deduplicates provider events and ignores stale subscription state while recording invoices and payments', function () {
    [, $business, $subscription] = createTrialBusiness();
    [, $price] = createBillingPlan('webhook', ['locations.max' => 1, 'staff.max' => 5, 'exports.enabled' => true, 'billing.manage' => true]);
    $subscription->update(['provider_customer_id' => 'cus_webhook', 'provider_subscription_id' => 'sub_webhook']);
    $processor = app(StripeWebhookProcessor::class);
    $created = now()->timestamp;
    $activePayload = stripeSubscriptionPayload('evt_active', $created, 'active', $price->provider_price_id);

    $processor->receiveVerified($activePayload);
    $processor->receiveVerified($activePayload);
    $processor->receiveVerified(stripeSubscriptionPayload('evt_stale', $created - 60, 'past_due', $price->provider_price_id));

    $invoicePayload = [
        'id' => 'evt_invoice', 'type' => 'invoice.paid', 'created' => $created + 60,
        'data' => ['object' => [
            'id' => 'in_goodhours', 'subscription' => 'sub_webhook', 'customer' => 'cus_webhook', 'number' => 'GH-0001',
            'status' => 'paid', 'currency' => 'usd', 'subtotal' => 2900, 'total' => 2900, 'amount_due' => 2900, 'amount_paid' => 2900,
            'created' => $created, 'status_transitions' => ['paid_at' => $created + 60],
            'payment_intent' => 'pi_goodhours', 'lines' => ['data' => [['period' => ['end' => $created + 2592000]]]],
        ]],
    ];
    $processor->receiveVerified($invoicePayload);

    expect(BillingProviderEvent::query()->count())->toBe(3)
        ->and($business->subscription()->first()->status)->toBe(SubscriptionStatus::Active)
        ->and(BillingInvoice::query()->where('business_id', $business->getKey())->count())->toBe(1)
        ->and(DB::table('billing_payments')->where('provider_payment_id', 'pi_goodhours')->where('status', 'succeeded')->exists())->toBeTrue();
});

it('rejects unsigned webhooks and accepts a valid Stripe signature', function () {
    config()->set('billing.stripe.webhook_secret', 'whsec_test_goodhours');
    $payload = json_encode(['id' => 'evt_signature', 'type' => 'unhandled.test', 'created' => now()->timestamp, 'data' => ['object' => []]], JSON_THROW_ON_ERROR);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_goodhours');

    $this->postJson(route('billing.webhooks.stripe'), json_decode($payload, true), ['Stripe-Signature' => 'bad'])->assertBadRequest();
    $this->call('POST', route('billing.webhooks.stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $payload)->assertNoContent();

    expect(BillingProviderEvent::query()->where('provider_event_id', 'evt_signature')->where('signature_verified', true)->exists())->toBeTrue();
});

it('prevents cross tenant billing and invoice access while preserving owner access', function () {
    [$owner, $business, $subscription] = createTrialBusiness();
    [$otherOwner, $otherBusiness] = createTrialBusiness();
    $invoice = BillingInvoice::query()->create([
        'business_id' => $business->getKey(), 'business_subscription_id' => $subscription->getKey(),
        'provider' => 'stripe', 'provider_invoice_id' => 'in_isolation', 'number' => 'GH-ISO', 'status' => 'paid',
        'currency' => 'USD', 'total_minor' => 1000, 'amount_paid_minor' => 1000, 'issued_at' => now(),
    ]);

    $this->actingAs($owner)->getJson(route('business.billing.invoices.show', [$business, $invoice->public_id]))->assertOk();
    $this->actingAs($owner)->get(route('business.billing.show', $business))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Billing/Overview')->where('businessLabel', $business->name)->where('exportAvailable', true));
    $this->actingAs($otherOwner)->getJson(route('business.billing.invoices.show', [$business, $invoice->public_id]))->assertForbidden();
    $this->actingAs($otherOwner)->getJson(route('business.billing.invoices.show', [$otherBusiness, $invoice->public_id]))->assertNotFound();
});

it('validates coupons and keeps export available through the documented termination window', function () {
    [, $business, $subscription] = createTrialBusiness();
    $coupon = BillingCoupon::query()->create([
        'code' => 'launch20', 'provider' => 'stripe', 'provider_coupon_id' => 'promo_launch20',
        'discount_type' => 'percent', 'discount_value' => 20, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDay(), 'is_active' => true,
    ]);
    expect($coupon->code)->toBe('LAUNCH20')->and($coupon->isRedeemable())->toBeTrue();

    $terminated = app(SubscriptionLifecycleManager::class)->terminate($subscription, now());
    expect($terminated->exportIsAvailable())->toBeTrue()
        ->and(app(EntitlementEvaluator::class)->decide($business, 'exports.enabled', 'export')->allowed)->toBeTrue();

    $terminated->update(['export_available_until' => now()->subSecond()]);
    expect(app(EntitlementEvaluator::class)->decide($business->fresh(), 'exports.enabled', 'export')->allowed)->toBeFalse();
});

it('allows a strongly authenticated platform administrator to perform a reasoned support cancellation', function () {
    [, $business] = createTrialBusiness();
    $administrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
    PlatformRoleAssignment::query()->create([
        'user_id' => $administrator->getKey(),
        'role' => 'platform_administrator',
        'reason' => 'Billing support test administrator.',
    ]);

    $this->actingAs($administrator)
        ->postJson(route('platform.businesses.billing.cancel', $business), ['reason' => 'Customer request GH-SUPPORT-42.'])
        ->assertOk()
        ->assertJsonPath('status', SubscriptionStatus::Canceled->value);

    expect($business->subscription()->first()->restriction_level)->toBe(RestrictionLevel::ReadOnly)
        ->and(DB::table('audit_events')->where('action', 'subscription.support_canceled')->where('actor_user_id', $administrator->getKey())->exists())->toBeTrue();
});

it('quarantines competing legacy billing admin resources', function () {
    expect(LemonSqueezyOrderResource::canAccess())->toBeFalse()
        ->and(LemonSqueezySubscriptionResource::canAccess())->toBeFalse()
        ->and(StripeOrderResource::canAccess())->toBeFalse()
        ->and(StripeSubscriptionResource::canAccess())->toBeFalse()
        ->and(ProductResource::canAccess())->toBeFalse()
        ->and(PriceResource::canAccess())->toBeFalse()
        ->and(InvoiceResource::canAccess())->toBeFalse();
});

it('delivers deduplicated renewal notices and safely reconciles a stored provider event', function () {
    Notification::fake();
    [$owner, , $subscription] = createTrialBusiness();
    [, $price] = createBillingPlan('notice', ['locations.max' => 1, 'staff.max' => 2, 'exports.enabled' => true, 'billing.manage' => true]);
    $active = app(SubscriptionLifecycleManager::class)->activate($subscription, $price, now(), now()->addMonth(), now());
    app(SubscriptionLifecycleManager::class)->renewalFailed($active, 1, now()->addMinute());

    $this->artisan('billing:send-notices')->assertSuccessful();
    Notification::assertSentTo($owner, BillingLifecycleNotice::class);
    expect(DB::table('billing_notices')->whereNotNull('sent_at')->count())->toBe(1);

    $payload = ['id' => 'evt_reconcile', 'type' => 'unknown.test', 'created' => now()->timestamp, 'data' => ['object' => []]];
    BillingProviderEvent::query()->create([
        'provider' => 'stripe', 'provider_event_id' => 'evt_reconcile', 'event_type' => 'unknown.test',
        'status' => 'failed', 'signature_verified' => true, 'provider_created_at' => now(),
        'attempts' => 1, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        'payload' => $payload, 'last_error' => 'Simulated recoverable failure.',
    ]);

    $this->artisan('billing:reconcile-provider-events')->assertSuccessful();
    expect(BillingProviderEvent::query()->where('provider_event_id', 'evt_reconcile')->value('status'))->toBe('ignored');
});

function stripeSubscriptionPayload(string $eventId, int $created, string $status, string $priceId): array
{
    return [
        'id' => $eventId, 'type' => 'customer.subscription.updated', 'created' => $created,
        'data' => ['object' => [
            'id' => 'sub_webhook', 'customer' => 'cus_webhook', 'status' => $status,
            'items' => ['data' => [[
                'price' => ['id' => $priceId],
                'current_period_start' => $created,
                'current_period_end' => $created + 2592000,
            ]]],
        ]],
    ];
}
