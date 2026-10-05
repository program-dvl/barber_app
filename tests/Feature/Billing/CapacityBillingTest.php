<?php

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\SmsCreditPurchase;
use App\Domain\Billing\Services\CapacityBillingManager;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use App\Domain\Billing\Services\CapacityStripeGateway;
use App\Domain\Billing\Services\CapacitySubscriptionProjector;
use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\EntitlementUsageManager;
use App\Domain\Billing\Services\SmsCreditWallet;
use App\Domain\Billing\Services\StripeWebhookProcessor;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Services\SmsSegmentCounter;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

uses(RefreshDatabase::class);
beforeEach(fn () => Carbon::setTestNow('2026-10-05 12:00:00'));
afterEach(fn () => Carbon::setTestNow());

function capacityRate(string $interval = 'monthly', string $market = 'US', string $currency = 'USD'): BillingRateCard
{
    $config = config('capacity-billing.markets.US');
    $config['currency'] = $currency;
    $config['approved'] = true;
    foreach (['monthly', 'annual'] as $cadence) {
        foreach (['base', 'location', 'staff'] as $role) {
            $config[$cadence][$role.'_price_id'] = 'price_capacity_'.$market.'_'.$cadence.'_'.$role;
        }
    }
    $config['sms_packs']['500']['price_id'] = 'price_capacity_'.$market.'_sms';
    config(['capacity-billing.enabled' => true, 'capacity-billing.markets.'.$market => $config,
        'billing.stripe.secret' => 'sk_test_local', 'billing.stripe.webhook_secret' => 'whsec_local']);
    $catalog = app(CapacityPricingCatalog::class);
    $terms = $catalog->terms($market, $interval);
    $price = BillingPlanPrice::create(['billing_plan_id' => BillingPlan::where('code', 'capacity')->value('id'),
        'billing_interval' => $interval, 'currency' => $currency, 'amount_minor' => $terms['base_minor'],
        'provider' => 'stripe', 'provider_price_id' => $terms['base_price_id'], 'catalog_managed' => true,
        'is_active' => true, 'effective_from' => now()->subDay()]);

    return BillingRateCard::create(['fingerprint' => $catalog->fingerprint($terms), 'market' => $market,
        'currency' => $currency, 'revision' => $terms['revision'], 'billing_interval' => $interval,
        'billing_plan_price_id' => $price->id, 'terms' => $terms, 'verified_at' => now()]);
}

function capacityFixture(int $locations = 1, int $staff = 1, string $interval = 'monthly'): array
{
    [$owner, $business] = createTenantMembership();
    $business->update(['country_code' => 'US']);
    $card = capacityRate($interval);
    $subscription = activateTestSubscription($business);
    $quote = app(CapacityPricingCatalog::class)->quote('US', $interval, $locations, $staff, $card);
    $subscription->update(['billing_plan_id' => BillingPlan::where('code', 'capacity')->value('id'),
        'billing_plan_price_id' => $card->billing_plan_price_id, 'billing_rate_card_id' => $card->id, 'capacity_snapshot' => $quote,
        'billing_interval' => $interval, 'current_period_started_at' => now()->subDays(10),
        'current_period_ends_at' => $interval === 'annual' ? now()->subDays(10)->addYear() : now()->addDays(20)]);

    return [$owner, $business, $subscription->fresh(), $card];
}

function capacityProvider(BillingRateCard $card, int $locations = 1, int $staff = 1): array
{
    $items = [];
    foreach (app(CapacityPricingCatalog::class)->items($card->terms, $locations, $staff) as $index => $item) {
        $role = $index === 0 ? 'base' : ($item['price'] === $card->terms['location_price_id'] ? 'location' : 'staff');
        $items[] = ['id' => 'si_'.$role, 'quantity' => $item['quantity'], 'price' => ['id' => $item['price'],
            'currency' => strtolower($card->currency), 'unit_amount' => $card->terms[$role.'_minor'],
            'recurring' => ['interval' => $card->billing_interval === 'annual' ? 'year' : 'month', 'interval_count' => 1]],
            'current_period_start' => now()->subDays(10)->timestamp, 'current_period_end' => now()->addDays(20)->timestamp];
    }

    return $items;
}

it('prices solo shops and larger teams without rounding or staff caps', function (int $locations, int $staff, int $total) {
    $quote = app(CapacityPricingCatalog::class)->quote('US', 'monthly', $locations, $staff);
    expect($quote['total_minor'])->toBe($total)->and($quote['ready'])->toBeFalse();
})->with([[1, 1, 2900], [1, 3, 4700], [1, 8, 9200], [3, 20, 23800], [5, 50, 54600]]);

it('does not present draft country prices as purchasable public prices', function () {
    expect(app(CapacityPricingCatalog::class)->present()['available'])->toBeFalse()
        ->and(app(CapacityPricingCatalog::class)->present(true)['markets'][0]['intervals']['monthly']['ready'])->toBeFalse();
    $this->artisan('billing:verify-capacity-rates US')->assertSuccessful();
    expect(BillingRateCard::count())->toBe(0);
});

it('requires both approval and an exactly verified rate revision', function () {
    capacityRate();
    config(['capacity-billing.markets.US.monthly.staff_minor' => 1000]);
    expect(app(CapacityPricingCatalog::class)->quote('US', 'monthly', 1, 3)['ready'])->toBeFalse();
});

it('uses local currency rate cards without exchanging customer invoices', function () {
    capacityRate('monthly', 'GB', 'GBP');
    expect(app(CapacityPricingCatalog::class)->quote('GB', 'monthly', 2, 4)['currency'])->toBe('GBP');
    expect(fn () => app(CapacityPricingCatalog::class)->quote('ZZ', 'monthly', 1, 1))->toThrow(ValidationException::class);
});

it('reviews expansion without granting capacity and claims the review once', function () {
    [$owner,$business,$subscription] = capacityFixture();
    $this->mock(CapacityStripeGateway::class)->shouldReceive('preview')->once()->andReturn(['due_today_minor' => 600, 'currency' => 'USD', 'tax_minor' => 0]);
    $manager = app(CapacityBillingManager::class);
    $change = $manager->review($business, $owner, 'US', 'monthly', 1, 2);
    expect($change->kind)->toBe('immediate')->and($subscription->fresh()->capacity_snapshot['staff'])->toBe(1);
    expect($manager->claim($business, $owner, $change)['dispatch'])->toBeTrue()
        ->and($manager->claim($business, $owner, $change)['dispatch'])->toBeFalse();
});

it('schedules reduced capacity at renewal and prevents deleting active resources', function () {
    [$owner,$business,$subscription] = capacityFixture(3, 8);
    $this->mock(CapacityStripeGateway::class)->shouldReceive('preview')->once()->withArgs(fn ($s, $q, $kind) => $kind === 'renewal')->andReturn(['due_today_minor' => 0, 'currency' => 'USD', 'tax_minor' => null]);
    $change = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 1, 2);
    expect($change->kind)->toBe('renewal')->and($change->effective_at->timestamp)->toBe($subscription->current_period_ends_at->timestamp)
        ->and($subscription->fresh()->capacity_snapshot['staff'])->toBe(8);
    StaffProfile::factory()->count(3)->create(['business_id' => $business->id, 'status' => 'active']);
    expect(fn () => app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 1, 2))->toThrow(ValidationException::class);
    expect($business->staffProfiles()->count())->toBe(3);
});

it('rejects stale and expired reviews before dispatching payment', function (string $kind) {
    [$owner,$business,$subscription] = capacityFixture();
    $this->mock(CapacityStripeGateway::class)->shouldReceive('preview')->once()->andReturn(['due_today_minor' => 600, 'currency' => 'USD', 'tax_minor' => 0]);
    $change = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 1, 2);
    if ($kind === 'stale') {
        $subscription->increment('version');
    } else {
        $change->update(['expires_at' => now()->subSecond()]);
    }
    $this->actingAs($owner)->postJson(route('business.billing.capacity.confirm', $business), ['change_id' => $change->public_id])->assertConflict();
})->with(['stale', 'expired']);

it('protects billing review and purchases from staff and other tenants', function () {
    [$staff,$business] = createTenantMembership(StarterRole::BarberStylist);
    activateTestSubscription($business);
    $this->actingAs($staff)->postJson(route('business.billing.capacity.review', $business), [])->assertForbidden();
    $this->actingAs($staff)->postJson(route('business.billing.sms.topup', $business), ['pack' => '500'])->assertForbidden();
    [$owner,$own] = createTenantMembership();
    activateTestSubscription($own);
    $this->actingAs($owner)->postJson(route('business.billing.capacity.confirm', $own), ['change_id' => str()->ulid()->toString()])->assertNotFound();
});

it('creates one capacity checkout and reuses its exact replay', function () {
    [$owner,$business] = createTenantMembership();
    $business->update(['country_code' => 'US']);
    capacityRate();
    $subscription = activateTestSubscription($business);
    $subscription->update(['provider_subscription_id' => null, 'status' => 'trialing']);
    $change = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 2, 3);
    $this->mock(SubscriptionProvider::class)->shouldReceive('createCheckout')->once()->withArgs(fn ($b, $price, $attempt) => $attempt->capacity_quote['locations'] === 2 && $attempt->capacity_quote['staff'] === 3)->andReturn(['url' => 'https://checkout.stripe.com/test', 'provider_session_id' => 'cs_capacity']);
    $payload = ['change_id' => $change->public_id];
    $this->actingAs($owner)->postJson(route('business.billing.capacity.confirm', $business), $payload)->assertCreated();
    $this->actingAs($owner)->postJson(route('business.billing.capacity.confirm', $business), $payload)->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/test');
    expect(BillingCheckoutAttempt::count())->toBe(1)->and($subscription->fresh()->capacity_snapshot)->toBeNull();
});

it('identifies the base item independently of provider item order', function () {
    $card = capacityRate();
    $items = array_reverse(capacityProvider($card, 3, 20));
    $result = app(CapacitySubscriptionProjector::class)->resolve($items);
    expect($result['quote']['total_minor'])->toBe(23800)->and($result['quote']['locations'])->toBe(3)->and($result['quote']['staff'])->toBe(20);
});

it('rejects unknown extra items mismatched currency and duplicate components', function (string $kind) {
    $card = capacityRate();
    $items = capacityProvider($card, 2, 3);
    if ($kind === 'unknown') {
        $items[1]['price']['id'] = 'price_unknown';
    } elseif ($kind === 'currency') {
        $items[1]['price']['currency'] = 'eur';
    } else {
        $items[2] = $items[1];
    }
    expect(app(CapacitySubscriptionProjector::class)->resolve($items))->toBeNull();
})->with(['unknown', 'currency', 'duplicate']);

it('applies signed active quantities and ignores stale provider expansions', function () {
    [,,$subscription,$card] = capacityFixture();
    $object = ['id' => $subscription->provider_subscription_id, 'object' => 'subscription', 'customer' => $subscription->provider_customer_id, 'status' => 'active', 'items' => ['data' => array_reverse(capacityProvider($card, 3, 20))]];
    $processor = app(StripeWebhookProcessor::class);
    expect($processor->synchronizeSubscriptionSnapshot($object, now()))->toBeTrue();
    expect($subscription->fresh()->capacity_snapshot['staff'])->toBe(20);
    $object['items']['data'] = capacityProvider($card, 1, 1);
    $processor->synchronizeSubscriptionSnapshot($object, now()->subMinute());
    expect($subscription->fresh()->capacity_snapshot['staff'])->toBe(20);
});

it('keeps current quantities when Stripe has a pending unpaid update', function () {
    [,,$subscription,$card] = capacityFixture();
    $object = ['id' => $subscription->provider_subscription_id, 'object' => 'subscription', 'customer' => $subscription->provider_customer_id, 'status' => 'active',
        'items' => ['data' => capacityProvider($card, 1, 1)], 'pending_update' => ['subscription_items' => capacityProvider($card, 3, 20)]];
    app(StripeWebhookProcessor::class)->synchronizeSubscriptionSnapshot($object, now());
    expect($subscription->fresh()->capacity_snapshot['staff'])->toBe(1);
});

it('reconciles a future capacity phase without reducing current access', function () {
    [$owner,$business,$subscription,$card] = capacityFixture(3, 20);
    $change = BillingCapacityChange::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id, 'actor_user_id' => $owner->id, 'subscription_version' => $subscription->version,
        'kind' => 'renewal', 'status' => 'submitted', 'quote' => app(CapacityPricingCatalog::class)->quote('US', 'monthly', 1, 3, $card), 'expires_at' => now()->addMinutes(10)]);
    $phase = ['subscription' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id, 'status' => 'active', 'phases' => [['start_date' => $subscription->current_period_ends_at->timestamp, 'items' => app(CapacityPricingCatalog::class)->items($card->terms, 1, 3)]]];
    app(StripeWebhookProcessor::class)->synchronizeScheduleSnapshot($phase, now());
    expect($change->fresh()->status)->toBe('scheduled')->and($subscription->fresh()->capacity_snapshot['staff'])->toBe(20);
});

it('counts a bookable person once across branches and excludes login-only administrators', function () {
    [,$business] = capacityFixture(2, 3);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'status' => 'active']);
    $locations = Location::factory()->count(2)->create(['business_id' => $business->id]);
    $staff->locations()->syncWithPivotValues($locations->pluck('id')->all(), ['business_id' => $business->id]);
    StaffProfile::factory()->create(['business_id' => $business->id, 'status' => 'inactive']);
    expect(app(EntitlementEvaluator::class)->usage($business, 'staff.max'))->toBe(1)
        ->and(app(EntitlementEvaluator::class)->value($business, 'staff.max'))->toBe(3);
});

it('resets included SMS monthly within an annual subscription and clamps anniversary dates', function () {
    [,$business,$subscription] = capacityFixture(1, 1, 'annual');
    $subscription->update(['current_period_started_at' => Carbon::parse('2026-01-31 12:00:00'), 'current_period_ends_at' => Carbon::parse('2027-01-31 12:00:00')]);
    $usage = app(EntitlementUsageManager::class);
    Carbon::setTestNow('2026-02-28 13:00:00');
    expect($usage->reserve($business, 'messaging.monthly_allowance', 100))->toBeTrue();
    $row = DB::table('entitlement_usage')->first();
    expect(Carbon::parse($row->period_ends_at)->toDateString())->toBe('2026-03-31');
    Carbon::setTestNow('2026-03-31 13:00:00');
    expect(app(EntitlementEvaluator::class)->usage($business, 'messaging.monthly_allowance'))->toBe(0)
        ->and($usage->reserve($business, 'messaging.monthly_allowance', 100))->toBeTrue();
});

it('adds prepaid credits only after exact owned payment and handles duplicate paid callbacks', function () {
    [$owner,$business,$subscription] = capacityFixture();
    $purchase = SmsCreditPurchase::create(['business_id' => $business->id, 'actor_user_id' => $owner->id, 'provider_session_id' => 'cs_paid_sms', 'status' => 'pending',
        'quote' => ['credits' => 500, 'amount_minor' => 1500, 'currency' => 'USD'], 'expires_at' => now()->addDay()]);
    $session = ['id' => 'cs_paid_sms', 'mode' => 'payment', 'customer' => $subscription->provider_customer_id, 'currency' => 'usd', 'amount_total' => 1500, 'status' => 'complete', 'payment_status' => 'unpaid',
        'metadata' => ['sms_purchase_id' => $purchase->public_id, 'business_public_id' => $business->public_id]];
    $wallet = app(SmsCreditWallet::class);
    expect($wallet->confirmPurchase($session))->toBeFalse()->and($wallet->balance($business->id))->toBe(0);
    $session['payment_status'] = 'paid';
    expect($wallet->confirmPurchase($session))->toBeTrue()->and($wallet->confirmPurchase($session))->toBeTrue()
        ->and($wallet->balance($business->id))->toBe(500)->and(DB::table('sms_credit_entries')->count())->toBe(1);
});

it('rejects topup currency amount and ownership mismatches', function (string $field) {
    [$owner,$business,$subscription] = capacityFixture();
    $purchase = SmsCreditPurchase::create(['business_id' => $business->id, 'actor_user_id' => $owner->id, 'provider_session_id' => 'cs_rejected', 'status' => 'pending', 'quote' => ['credits' => 500, 'amount_minor' => 1500, 'currency' => 'USD'], 'expires_at' => now()->addDay()]);
    $session = ['id' => 'cs_rejected', 'mode' => 'payment', 'customer' => $subscription->provider_customer_id, 'currency' => 'usd', 'amount_total' => 1500, 'status' => 'complete', 'payment_status' => 'paid', 'metadata' => ['sms_purchase_id' => $purchase->public_id, 'business_public_id' => $business->public_id]];
    $session[$field] = $field === 'amount_total' ? 1 : 'different';
    expect(app(SmsCreditWallet::class)->confirmPurchase($session))->toBeFalse()->and(app(SmsCreditWallet::class)->balance($business->id))->toBe(0);
})->with(['customer', 'currency', 'amount_total', 'id']);

it('reserves complete segment cost without overspending and releases exactly once', function () {
    [,$business] = capacityFixture();
    $message = capacityMessage($business);
    $wallet = app(SmsCreditWallet::class);
    DB::table('sms_credit_entries')->insert(['business_id' => $business->id, 'source_key' => 'test:initial', 'quantity' => 2, 'kind' => 'purchase', 'created_at' => now()]);
    app(EntitlementUsageManager::class)->reserve($business, 'messaging.monthly_allowance', 99);
    expect($wallet->reserve($message, 4))->toBeFalse()->and($wallet->balance($business->id))->toBe(2);
    expect($wallet->reserve($message, 3))->toBeTrue()->and($wallet->reserve($message, 3))->toBeTrue()->and($wallet->balance($business->id))->toBe(0);
    $wallet->release($message);
    $wallet->release($message);
    expect($wallet->balance($business->id))->toBe(2)->and(app(EntitlementEvaluator::class)->usage($business, 'messaging.monthly_allowance'))->toBe(99);
});

it('counts GSM extensions unicode and emojis as actual SMS segments', function () {
    $counter = app(SmsSegmentCounter::class);
    expect($counter->segments(str_repeat('a', 160)))->toBe(1)->and($counter->segments(str_repeat('a', 161)))->toBe(2)
        ->and($counter->segments(str_repeat('^', 81)))->toBe(2)->and($counter->segments(str_repeat('你', 71)))->toBe(2)
        ->and($counter->segments(str_repeat('😀', 36)))->toBe(2)
        ->and($counter->credits('Hi', '+441234567890', ['+1' => 1]))->toBeNull()
        ->and($counter->credits('Hi', '+441234567890', ['+4' => 8, '+44' => 2]))->toBe(2);
});

it('shows the total capacity charge rather than just the base item on billing', function () {
    [$owner,$business] = capacityFixture(3, 20);
    $this->actingAs($owner)->get(route('business.billing.show', $business))->assertInertia(fn (Assert $page) => $page
        ->where('subscription.price.amount_minor', 23800)->where('entitlements', fn ($items) => $items['staff.max'] === 20 && $items['messaging.monthly_allowance'] === 2000)
        ->where('subscription.capacity.locations', 3)->where('smsCredits', 0));
});

function capacityMessage($business): CommunicationMessage
{
    // Real reservation tests use an intent-owned message because foreign keys are enforced.
    $template = CommunicationTemplate::firstOrCreate(['business_id' => $business->id, 'intent_type' => 'appointment_confirmation', 'channel' => 'sms', 'locale' => 'en', 'version' => 1], ['status' => 'published', 'category' => 'transactional', 'body' => 'Test', 'variables' => [], 'fallbacks' => []]);
    $intent = CommunicationIntent::create(['business_id' => $business->id, 'event_type' => 'test', 'legal_basis' => 'service_transaction', 'locale' => 'en', 'time_zone' => 'UTC', 'scheduled_for_utc' => now(), 'local_scheduled_for' => now()->toIso8601String(), 'event_key' => str()->uuid()->toString(), 'intent_type' => 'appointment_confirmation', 'category' => 'transactional', 'source_type' => 'test', 'source_id' => 1, 'correlation_id' => str()->uuid(), 'status' => 'queued']);
    $message = CommunicationMessage::create(['business_id' => $business->id, 'communication_intent_id' => $intent->id, 'communication_template_id' => $template->id, 'channel' => 'sms', 'recipient' => '+12025550123', 'recipient_hash' => hash('sha256', 'recipient'), 'legal_basis' => 'service_transaction', 'locale' => 'en', 'time_zone' => 'UTC', 'template_variables' => [], 'queued_at' => now(), 'idempotency_key' => str()->uuid()->toString(), 'status' => 'queued', 'category' => 'transactional']);

    return $message;
}

it('spends prepaid credits after included allowance is fully used and blocks read-only sends', function () {
    [,$business,$subscription] = capacityFixture();
    app(EntitlementUsageManager::class)->reserve($business, 'messaging.monthly_allowance', 100);
    DB::table('sms_credit_entries')->insert(['business_id' => $business->id, 'source_key' => 'test:full', 'quantity' => 5, 'kind' => 'purchase', 'created_at' => now()]);
    $wallet = app(SmsCreditWallet::class);
    expect($wallet->reserve(capacityMessage($business), 3))->toBeTrue()->and($wallet->balance($business->id))->toBe(2);
    $subscription->update(['restriction_level' => 'read_only']);
    expect($wallet->reserve(capacityMessage($business), 1))->toBeFalse()->and($wallet->balance($business->id))->toBe(2);
});

it('verifies asynchronous taxed text payments once without accepting discounted or wrong totals', function () {
    [$owner,$business,$subscription] = capacityFixture();
    $purchase = SmsCreditPurchase::create(['business_id' => $business->id, 'actor_user_id' => $owner->id, 'provider_session_id' => 'cs_async_sms', 'status' => 'pending',
        'quote' => ['credits' => 500, 'amount_minor' => 1500, 'currency' => 'USD'], 'expires_at' => now()->addDay()]);
    $object = ['id' => 'cs_async_sms', 'mode' => 'payment', 'customer' => $subscription->provider_customer_id, 'currency' => 'usd',
        'amount_subtotal' => 1500, 'amount_total' => 1620, 'total_details' => ['amount_tax' => 120, 'amount_discount' => 0],
        'status' => 'complete', 'payment_status' => 'paid', 'metadata' => ['sms_purchase_id' => $purchase->public_id, 'business_public_id' => $business->public_id]];
    $forged = $object;
    $forged['amount_total'] = 1500;
    expect(app(SmsCreditWallet::class)->confirmPurchase($forged))->toBeFalse();
    $forged = $object;
    $forged['total_details']['amount_discount'] = 100;
    expect(app(SmsCreditWallet::class)->confirmPurchase($forged))->toBeFalse();
    app(StripeWebhookProcessor::class)->receiveVerified(['id' => 'evt_sms_async', 'type' => 'checkout.session.async_payment_succeeded', 'created' => now()->timestamp, 'data' => ['object' => $object]]);
    expect(app(SmsCreditWallet::class)->balance($business->id))->toBe(500);
});

it('closes overlapping legacy annual allowance when explicitly migrating to monthly credit windows', function () {
    [,$business,$subscription,$card] = capacityFixture(1, 1, 'annual');
    $subscription->update(['billing_plan_id' => BillingPlan::where('code', 'starter')->value('id'), 'capacity_snapshot' => null, 'billing_rate_card_id' => null, 'current_period_started_at' => now()->subMonths(3), 'current_period_ends_at' => now()->addMonths(9)]);
    app(EntitlementUsageManager::class)->reserve($business, 'messaging.monthly_allowance', 15);
    $object = ['id' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id, 'status' => 'active', 'items' => ['data' => capacityProvider($card, 1, 1)]];
    app(StripeWebhookProcessor::class)->synchronizeSubscriptionSnapshot($object, now());
    expect(app(EntitlementEvaluator::class)->usage($business, 'messaging.monthly_allowance'))->toBe(0)
        ->and(DB::table('entitlement_usage')->sum('quantity'))->toBe(15);
    expect(app(EntitlementUsageManager::class)->reserve($business, 'messaging.monthly_allowance', 100))->toBeTrue();
});

it('releases an expired pending expansion but ignores an older expiration', function () {
    [$owner,$business,$subscription,$card] = capacityFixture();
    $subscription->update(['provider_state_at' => now()->subMinute()]);
    $change = BillingCapacityChange::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id, 'actor_user_id' => $owner->id, 'subscription_version' => $subscription->version,
        'kind' => 'immediate', 'status' => 'submitted', 'submitted_at' => now(), 'quote' => app(CapacityPricingCatalog::class)->quote('US', 'monthly', 1, 3, $card), 'expires_at' => now()->addMinutes(10)]);
    $processor = app(StripeWebhookProcessor::class);
    $payload = ['id' => 'evt_old_expiry', 'type' => 'customer.subscription.pending_update_expired', 'created' => now()->subHours(2)->timestamp, 'data' => ['object' => ['id' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id]]];
    $processor->receiveVerified($payload);
    expect($change->fresh()->status)->toBe('submitted');
    $payload['id'] = 'evt_new_expiry';
    $payload['created'] = now()->timestamp;
    $processor->receiveVerified($payload);
    expect($change->fresh()->status)->toBe('expired')->and($subscription->fresh()->capacity_snapshot['staff'])->toBe(1);
});

it('rejects provider quantities that renew on different dates', function () {
    $card = capacityRate();
    $items = capacityProvider($card, 2, 3);
    $items[1]['current_period_end']++;
    expect(app(CapacitySubscriptionProjector::class)->resolve($items))->toBeNull();
});

it('recovers an uncertain checkout using the same attempt and payment key', function () {
    [$owner,$business] = createTenantMembership();
    $business->update(['country_code' => 'US']);
    $card = capacityRate();
    $subscription = activateTestSubscription($business);
    $subscription->update(['provider_subscription_id' => null, 'status' => 'trialing']);
    $change = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 2, 3);
    $change->update(['status' => 'submitted', 'submitted_at' => now()]);
    $attempt = BillingCheckoutAttempt::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id, 'created_by_user_id' => $owner->id,
        'billing_plan_price_id' => $card->billing_plan_price_id, 'provider' => 'stripe', 'provider_transaction_id' => 'pending_capacity_'.$change->public_id, 'status' => 'pending', 'expires_at' => now()->addHour(), 'capacity_quote' => $change->quote]);
    $this->mock(SubscriptionProvider::class)->shouldReceive('createCheckout')->once()->withArgs(fn ($b, $price, $used) => $used->id === $attempt->id)->andReturn(['url' => 'https://checkout.stripe.com/recovered', 'provider_session_id' => 'cs_recovered']);
    $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('checkout_url', 'https://checkout.stripe.com/recovered');
    expect(BillingCheckoutAttempt::count())->toBe(1)->and($subscription->fresh()->capacity_snapshot)->toBeNull();
});

function withCapacityStripeTransport(array $subscription, callable $operation): void
{
    $client = new class($subscription) implements ClientInterface
    {
        public array $requests = [];

        public function __construct(private array $subscription) {}

        public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'params' => $params];
            $object = str_contains($url, 'subscription_schedules') ? ['object' => 'subscription_schedule', 'id' => 'sub_sched_capacity'] : $this->subscription;

            return [json_encode($object), 200, []];
        }
    };
    ApiRequestor::setHttpClient($client);
    try {
        $operation($client);
    } finally {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }
}

it('sends pending payment and fixed proration with all component quantities through the Stripe SDK', function () {
    [$owner,$business,$subscription,$card] = capacityFixture(2, 3);
    $quote = app(CapacityPricingCatalog::class)->quote('US', 'monthly', 3, 5, $card);
    $quote['proration_date'] = now()->timestamp;
    $change = BillingCapacityChange::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id, 'actor_user_id' => $owner->id, 'subscription_version' => $subscription->version, 'kind' => 'immediate', 'status' => 'submitted', 'quote' => $quote, 'expires_at' => now()->addMinutes(10)]);
    $object = ['object' => 'subscription', 'id' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id, 'status' => 'active', 'cancel_at_period_end' => false, 'pending_update' => null, 'schedule' => null, 'items' => ['object' => 'list', 'data' => capacityProvider($card, 2, 3)], 'discounts' => []];
    withCapacityStripeTransport($object, function ($client) use ($subscription, $change) {
        app(CapacityStripeGateway::class)->submit($subscription, $change);
        $request = $client->requests[1]['params'];
        expect($request['payment_behavior'])->toBe('pending_if_incomplete')->and($request['proration_date'])->toBe(now()->timestamp)
            ->and($request['proration_behavior'])->toBe('always_invoice')->and($request['items'][1])->toBe(['id' => 'si_location', 'quantity' => 2])
            ->and($request['items'][2])->toBe(['id' => 'si_staff', 'quantity' => 4]);
    });
});

it('creates a finite renewal schedule and retains automatic tax through the Stripe SDK', function () {
    [$owner,$business,$subscription,$card] = capacityFixture(3, 20);
    $quote = app(CapacityPricingCatalog::class)->quote('US', 'monthly', 1, 3, $card);
    $change = BillingCapacityChange::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id, 'actor_user_id' => $owner->id, 'subscription_version' => $subscription->version, 'kind' => 'renewal', 'status' => 'submitted', 'quote' => $quote, 'expires_at' => now()->addMinutes(10)]);
    $object = ['object' => 'subscription', 'id' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id, 'status' => 'active', 'cancel_at_period_end' => false, 'pending_update' => null, 'schedule' => null, 'items' => ['object' => 'list', 'data' => capacityProvider($card, 3, 20)], 'discounts' => [], 'automatic_tax' => ['enabled' => true], 'default_tax_rates' => []];
    withCapacityStripeTransport($object, function ($client) use ($subscription, $change, $card) {
        app(CapacityStripeGateway::class)->submit($subscription,$change);
        $request = $client->requests[2]['params'];
        expect($request['end_behavior'])->toBe('release')->and($request['phases'][1]['duration'])->toBe(['interval' => 'month', 'interval_count' => 1])
            ->and($request['phases'][1]['automatic_tax']['enabled'])->toBe('true')
            ->and($request['phases'][1]['start_date'])->toBe($subscription->current_period_ends_at->timestamp)
            ->and($request['phases'][1]['items'])->toBe(app(CapacityPricingCatalog::class)->items($card->terms,1,3));
    });
});
