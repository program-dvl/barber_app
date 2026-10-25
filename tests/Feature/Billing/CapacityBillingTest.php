<?php

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\SmsCreditPurchase;
use App\Domain\Billing\Providers\StripeSubscriptionProvider;
use App\Domain\Billing\Services\CapacityBillingManager;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use App\Domain\Billing\Services\CapacityStripeGateway;
use App\Domain\Billing\Services\CapacitySubscriptionProjector;
use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\EntitlementUsageManager;
use App\Domain\Billing\Services\SmsCreditWallet;
use App\Domain\Billing\Services\StripeCheckoutReconciler;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);
beforeEach(fn () => Carbon::setTestNow(Carbon::now()->startOfSecond()->addMinutes(5)));
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
    $config['sms_packs']['500'] = ['credits' => 500, 'amount_minor' => 1500, 'price_id' => 'price_capacity_'.$market.'_sms'];
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
            'currency' => strtolower($card->currency),
            ...(($role === 'staff' && ! empty($card->terms['staff_tiers'])) ? ['unit_amount' => null, 'billing_scheme' => 'tiered', 'tiers_mode' => 'graduated'] : ['unit_amount' => $card->terms[$role.'_minor']]),
            'recurring' => ['interval' => $card->billing_interval === 'annual' ? 'year' : 'month', 'interval_count' => 1]],
            'current_period_start' => now()->subDays(10)->timestamp, 'current_period_end' => ($card->billing_interval === 'annual' ? now()->subDays(10)->addYear() : now()->addDays(20))->timestamp];
    }

    return $items;
}

it('prices solo shops and larger teams without rounding or staff caps', function (int $locations, int $staff, int $total) {
    $quote = app(CapacityPricingCatalog::class)->quote('US', 'monthly', $locations, $staff);
    expect($quote['total_minor'])->toBe($total)->and($quote['ready'])->toBeFalse();
})->with([[1, 1, 2900], [1, 3, 4700], [1, 2, 3800], [1, 5, 6500], [1, 6, 7100], [1, 10, 9500], [1, 11, 9900], [1, 20, 13500], [3, 20, 17300], [4, 20, 19200], [5, 50, 33100]]);

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
    $this->mock(StripeCheckoutReconciler::class)->shouldReceive('reconcile')->once()->andReturnUsing(fn ($attempt) => $attempt);
    $this->actingAs($owner)->postJson(route('business.billing.capacity.confirm', $business), $payload)->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/test');
    expect(BillingCheckoutAttempt::count())->toBe(1)->and($subscription->fresh()->capacity_snapshot)->toBeNull();
});

it('identifies the base item independently of provider item order', function () {
    $card = capacityRate();
    $items = array_reverse(capacityProvider($card, 3, 20));
    $result = app(CapacitySubscriptionProjector::class)->resolve($items);
    expect($result['quote']['total_minor'])->toBe(17300)->and($result['quote']['locations'])->toBe(3)->and($result['quote']['staff'])->toBe(20);
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
        ->where('subscription.price.amount_minor', 17300)->where('entitlements', fn ($items) => $items['staff.max'] === 20 && $items['messaging.monthly_allowance'] === 2000)
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
            if ($method === 'post' && str_contains($url, '/checkout/sessions')) {
                $object = ['object' => 'checkout.session', 'id' => 'cs_graduated_sdk', 'url' => 'https://checkout.stripe.com/graduated'];
            }

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
    $quote = app(CapacityPricingCatalog::class)->quote('US', 'monthly', 3, 20, $card);
    $quote['proration_date'] = now()->timestamp;
    $change = BillingCapacityChange::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id, 'actor_user_id' => $owner->id, 'subscription_version' => $subscription->version, 'kind' => 'immediate', 'status' => 'submitted', 'quote' => $quote, 'expires_at' => now()->addMinutes(10)]);
    $object = ['object' => 'subscription', 'id' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id, 'status' => 'active', 'cancel_at_period_end' => false, 'pending_update' => null, 'schedule' => null, 'items' => ['object' => 'list', 'data' => capacityProvider($card, 2, 3)], 'discounts' => []];
    withCapacityStripeTransport($object, function ($client) use ($subscription, $change) {
        app(CapacityStripeGateway::class)->submit($subscription, $change);
        $request = $client->requests[1]['params'];
        expect($request['payment_behavior'])->toBe('pending_if_incomplete')->and($request['proration_date'])->toBe(now()->timestamp)
            ->and($request['proration_behavior'])->toBe('always_invoice')->and($request['items'][1])->toBe(['id' => 'si_location', 'quantity' => 2])
            ->and($request['items'][2])->toBe(['id' => 'si_staff', 'quantity' => 19]);
    });
});

it('creates a finite renewal schedule and retains automatic tax through the Stripe SDK', function () {
    [$owner,$business,$subscription,$card] = capacityFixture(3, 20);
    $quote = app(CapacityPricingCatalog::class)->quote('US', 'monthly', 1, 3, $card);
    $change = BillingCapacityChange::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id, 'actor_user_id' => $owner->id, 'subscription_version' => $subscription->version, 'kind' => 'renewal', 'status' => 'submitted', 'quote' => $quote, 'expires_at' => now()->addMinutes(10)]);
    $object = ['object' => 'subscription', 'id' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id, 'status' => 'active', 'cancel_at_period_end' => false, 'pending_update' => null, 'schedule' => null, 'items' => ['object' => 'list', 'data' => capacityProvider($card, 3, 20)], 'discounts' => [], 'automatic_tax' => ['enabled' => true], 'default_tax_rates' => []];
    withCapacityStripeTransport($object, function ($client) use ($subscription, $change, $card) {
        app(CapacityStripeGateway::class)->submit($subscription, $change);
        $request = $client->requests[2]['params'];
        expect($request['end_behavior'])->toBe('release')->and($request['phases'][1]['duration'])->toBe(['interval' => 'month', 'interval_count' => 1])
            ->and($request['phases'][1]['automatic_tax']['enabled'])->toBe('true')
            ->and($request['phases'][1]['start_date'])->toBe($subscription->current_period_ends_at->timestamp)
            ->and($request['phases'][1]['items'])->toBe(app(CapacityPricingCatalog::class)->items($card->terms, 1, 3));
    });
});

it('resumes an open checkout and releases the request only after provider expiry', function (string $status) {
    [$owner, $business] = createTenantMembership();
    $card = capacityRate();
    $subscription = activateTestSubscription($business);
    $subscription->update(['status' => 'trialing', 'provider_subscription_id' => null]);
    $change = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 1, 4);
    $change->update(['status' => 'submitted', 'submitted_at' => now()]);
    $attempt = BillingCheckoutAttempt::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id,
        'created_by_user_id' => $owner->id, 'billing_plan_price_id' => $card->billing_plan_price_id,
        'provider' => 'stripe', 'provider_transaction_id' => 'cs_resume_capacity', 'provider_checkout_url' => 'https://checkout.stripe.com/resume',
        'status' => 'pending', 'expires_at' => now()->addHours(2), 'capacity_quote' => $change->quote]);
    $object = ['object' => 'checkout.session', 'id' => 'cs_resume_capacity', 'status' => $status, 'mode' => 'subscription', 'subscription' => null,
        'customer' => $subscription->provider_customer_id, 'metadata' => ['application' => 'clipperdesk', 'business_public_id' => $business->public_id,
            'billing_checkout_attempt_id' => $attempt->public_id, 'plan_price_id' => (string) $card->billing_plan_price_id]];
    withCapacityStripeTransport($object, function () use ($owner, $business, $change, $status) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('checkout_url', $status === 'open' ? 'https://checkout.stripe.com/resume' : null)
            ->assertJsonPath('status', $status === 'open' ? 'submitted' : 'expired');
    });
    expect($subscription->fresh()->status->value)->toBe('trialing')->and(BillingCheckoutAttempt::count())->toBe(1);
})->with(['open', 'expired']);

it('charges exactly ten months for annual progressive capacity', function (int $locations, int $staff, int $monthly) {
    $catalog = app(CapacityPricingCatalog::class);
    expect($catalog->quote('US', 'monthly', $locations, $staff)['total_minor'])->toBe($monthly)
        ->and($catalog->quote('US', 'annual', $locations, $staff)['total_minor'])->toBe($monthly * 10);
})->with([[1, 1, 2900], [1, 2, 3800], [1, 5, 6500], [1, 6, 7100], [1, 10, 9500], [1, 11, 9900], [1, 20, 13500], [4, 20, 19200], [5, 50, 33100]]);

it('rejects invalid capacity before quoting or invoking Stripe', function (int $locations, int $staff) {
    expect(fn () => app(CapacityPricingCatalog::class)->quote('US', 'monthly', $locations, $staff))->toThrow(ValidationException::class);
})->with([[0, 1], [1, 0], [-1, 1], [1, -1], [1001, 1], [1, 10001]]);

it('preserves immutable flat staff terms and allows only a reviewed renewal migration', function () {
    [$owner, $business, $subscription, $modern] = capacityFixture(1, 20);
    $catalog = app(CapacityPricingCatalog::class);
    $terms = $modern->terms;
    unset($terms['staff_tiers']);
    $terms['revision'] = 'retained-flat';
    foreach (['base', 'location', 'staff'] as $role) {
        $terms[$role.'_price_id'] = 'price_retained_'.$role;
    }
    $price = BillingPlanPrice::create(['billing_plan_id' => $subscription->billing_plan_id, 'provider' => 'stripe', 'provider_price_id' => $terms['base_price_id'], 'currency' => 'USD', 'billing_interval' => 'monthly', 'amount_minor' => 2900, 'is_active' => true, 'effective_from' => now()->subDays(2)]);
    $legacy = BillingRateCard::create(['fingerprint' => $catalog->fingerprint($terms), 'market' => 'US', 'currency' => 'USD', 'revision' => $terms['revision'], 'billing_interval' => 'monthly', 'billing_plan_price_id' => $price->id, 'terms' => $terms, 'verified_at' => now()]);
    $quote = $catalog->quote('US', 'monthly', 1, 20, $legacy);
    $subscription->update(['billing_plan_price_id' => $price->id, 'billing_rate_card_id' => $legacy->id, 'capacity_snapshot' => $quote]);
    expect($quote['total_minor'])->toBe(20000)->and($quote['sms_monthly_allowance'])->toBe(2000);
    expect(app(CapacitySubscriptionProjector::class)->resolve(capacityProvider($legacy, 1, 20))['quote']['total_minor'])->toBe(20000);
    $this->mock(CapacityStripeGateway::class)->shouldReceive('preview')->once()->withArgs(fn ($s, $q, $kind) => $q['total_minor'] === 20900 && $kind === 'immediate')->andReturn(['due_today_minor' => 600, 'currency' => 'USD', 'tax_minor' => 0]);
    $retained = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 1, 21);
    expect($retained->quote['revision'])->toBe('retained-flat');
    $this->mock(CapacityStripeGateway::class)->shouldReceive('preview')->once()->withArgs(fn ($s, $q, $kind) => $q['total_minor'] === 13500 && $kind === 'renewal')->andReturn(['due_today_minor' => 0, 'currency' => 'USD', 'tax_minor' => null]);
    $this->actingAs($owner)->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 1, 'staff' => 20, 'use_current_rates' => true, 'total_minor' => 1])
        ->assertOk()->assertJsonPath('kind', 'renewal')->assertJsonPath('quote.total_minor', 13500);
    expect($subscription->fresh()->capacity_snapshot['total_minor'])->toBe(20000)->and($legacy->fresh()->terms)->toBe($terms);
});

it('rejects volume pricing and malformed progressive provider evidence', function (string $kind) {
    $card = capacityRate();
    $items = capacityProvider($card, 1, 20);
    if ($kind === 'volume') {
        $items[1]['price']['tiers_mode'] = 'volume';
    }
    if ($kind === 'per_unit') {
        $items[1]['price']['billing_scheme'] = 'per_unit';
    }
    if ($kind === 'tiers') {
        $items[1]['price']['tiers'] = [['up_to' => 4, 'unit_amount' => 900], ['up_to' => 9, 'unit_amount' => 600], ['up_to' => null, 'unit_amount' => 500]];
    }
    expect(app(CapacitySubscriptionProjector::class)->resolve($items))->toBeNull();
})->with(['volume', 'per_unit', 'tiers']);

it('fails closed on malformed progressive pricing configuration', function (array $tiers) {
    config(['capacity-billing.markets.US.monthly.staff_tiers' => $tiers]);
    expect(fn () => app(CapacityPricingCatalog::class)->quote('US', 'monthly', 1, 20))->toThrow(ValidationException::class);
})->with([
    [[]],
    [[['up_to' => 4, 'unit_minor' => 900]]],
    [[['up_to' => 4, 'unit_minor' => 900], ['up_to' => 3, 'unit_minor' => 600], ['up_to' => null, 'unit_minor' => 400]]],
    [[['up_to' => null, 'unit_minor' => -1]]],
    [[['up_to' => null, 'unit_minor' => 4.5]]],
]);

it('uses server priced graduated items for annual Stripe checkout and preserves the trial until provider confirmation', function () {
    [$owner, $business, $subscription, $card] = capacityFixture(1, 1, 'annual');
    $subscription->update(['provider_subscription_id' => null, 'status' => 'trialing', 'capacity_snapshot' => null, 'billing_rate_card_id' => null]);
    $quote = app(CapacityPricingCatalog::class)->quote('US', 'annual', 5, 50, $card);
    $attempt = BillingCheckoutAttempt::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id, 'created_by_user_id' => $owner->id,
        'billing_plan_price_id' => $card->billing_plan_price_id, 'provider' => 'stripe', 'provider_transaction_id' => 'pending_graduated_sdk', 'status' => 'pending', 'expires_at' => now()->addHour(), 'capacity_quote' => $quote]);
    withCapacityStripeTransport([], function ($client) use ($business, $card, $attempt) {
        $result = app(StripeSubscriptionProvider::class)->createCheckout($business, $attempt->price, $attempt, 'https://example.test/success', 'https://example.test/cancel');
        expect($result['provider_session_id'])->toBe('cs_graduated_sdk');
        $params = $client->requests[0]['params'];
        expect($params['line_items'])->toBe([['price' => $card->terms['base_price_id'], 'quantity' => 1], ['price' => $card->terms['location_price_id'], 'quantity' => 4], ['price' => $card->terms['staff_price_id'], 'quantity' => 49]])
            ->and($params['mode'])->toBe('subscription')->and($params['allow_promotion_codes'])->toBe('false');
    });
    expect($quote['total_minor'])->toBe(331000)->and($subscription->fresh()->status->value)->toBe('trialing')->and($subscription->fresh()->capacity_snapshot)->toBeNull();
    $attempt->update(['provider_transaction_id' => 'cs_graduated_sdk']);
    app(StripeWebhookProcessor::class)->receiveVerified(['id' => 'evt_graduated_checkout', 'type' => 'checkout.session.completed', 'created' => now()->timestamp,
        'data' => ['object' => ['object' => 'checkout.session', 'id' => 'cs_graduated_sdk', 'mode' => 'subscription', 'subscription' => 'sub_graduated_paid', 'customer' => $subscription->provider_customer_id,
            'client_reference_id' => $business->public_id, 'metadata' => ['business_public_id' => $business->public_id, 'billing_checkout_attempt_id' => $attempt->public_id, 'plan_price_id' => (string) $card->billing_plan_price_id]]]]);
    expect($subscription->fresh()->status->value)->toBe('trialing')->and($attempt->fresh()->status)->toBe('processing');
    $object = ['id' => 'sub_graduated_paid', 'object' => 'subscription', 'customer' => $subscription->provider_customer_id, 'status' => 'active',
        'metadata' => ['business_public_id' => $business->public_id], 'items' => ['data' => capacityProvider($card, 5, 50)]];
    expect(app(StripeWebhookProcessor::class)->synchronizeSubscriptionSnapshot($object, now()))->toBeTrue();
    expect($subscription->fresh()->status->value)->toBe('active')->and($subscription->fresh()->capacity_snapshot['total_minor'])->toBe(331000);
});

function abandonedCapacityCheckout(string $sessionId = 'cs_test_abandoned', string $interval = 'monthly'): array
{
    [$owner, $business] = createTenantMembership();
    $card = capacityRate($interval);
    $subscription = activateTestSubscription($business);
    $subscription->update(['status' => 'trialing', 'provider_subscription_id' => null]);
    $change = app(CapacityBillingManager::class)->review($business, $owner, 'US', $interval, 3, 10);
    $change->update(['status' => 'submitted', 'submitted_at' => now()]);
    $attempt = BillingCheckoutAttempt::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id,
        'created_by_user_id' => $owner->id, 'billing_plan_price_id' => $card->billing_plan_price_id,
        'provider' => 'stripe', 'provider_transaction_id' => $sessionId, 'provider_checkout_url' => 'https://checkout.stripe.com/abandoned',
        'status' => 'pending', 'expires_at' => now()->addHours(2), 'capacity_quote' => [...$change->quote, 'capacity_change_id' => $change->public_id,
            'stripe_mode' => 'test', 'stripe_credential_fingerprint' => hash('sha256', config('billing.stripe.secret'))]]);
    $session = ['object' => 'checkout.session', 'id' => $sessionId, 'status' => 'open', 'mode' => 'subscription', 'subscription' => null,
        'customer' => $subscription->provider_customer_id, 'client_reference_id' => $business->public_id, 'livemode' => str_starts_with($sessionId, 'cs_live_'),
        'url' => $attempt->provider_checkout_url, 'expires_at' => now()->addDay()->timestamp,
        'metadata' => ['application' => 'clipperdesk', 'business_public_id' => $business->public_id,
            'billing_checkout_attempt_id' => $attempt->public_id, 'plan_price_id' => (string) $card->billing_plan_price_id]];

    return [$owner, $business, $subscription->fresh(), $card, $change, $attempt, $session];
}

function withCheckoutRecoveryStripe(array $session, callable $operation): void
{
    $client = new class($session) implements ClientInterface
    {
        public array $requests = [];

        public ?string $failure = null;

        public bool $raceCompletion = false;

        public array $listed = [];

        public bool $hasMore = false;

        public function __construct(public array $session) {}

        public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $path = parse_url($url, PHP_URL_PATH);
            $this->requests[] = ['method' => $method, 'path' => $path, 'params' => $params, 'headers' => $headers];
            if ($this->failure) {
                return [json_encode(['error' => ['type' => 'invalid_request_error', 'code' => $this->failure, 'message' => 'Private provider diagnostic']]), $this->failure === 'resource_missing' ? 404 : 503, []];
            }
            if (str_ends_with($path, '/expire')) {
                if ($this->raceCompletion) {
                    $this->session['status'] = 'complete';

                    return [json_encode(['error' => ['type' => 'invalid_request_error', 'message' => 'Already completed']]), 400, []];
                }
                $this->session['status'] = 'expired';
                $this->session['url'] = null;
                $object = $this->session;
            } elseif ($path === '/v1/customers' && strtolower($method) === 'post') {
                $object = ['object' => 'customer', 'id' => 'cus_live_recovered', 'metadata' => $params['metadata']];
            } elseif ($path === '/v1/checkout/sessions' && strtolower($method) === 'post') {
                $this->session = [...$this->session, 'id' => str_contains(config('billing.stripe.secret'), '_live_') ? 'cs_live_recovered' : 'cs_test_recovered', 'status' => 'open', 'livemode' => str_contains(config('billing.stripe.secret'), '_live_'),
                    'customer' => $params['customer'], 'metadata' => $params['metadata'], 'client_reference_id' => $params['client_reference_id'],
                    'url' => 'https://checkout.stripe.com/recovered'];
                $object = $this->session;
            } elseif (str_contains($path, '/customers/')) {
                $object = ['object' => 'customer', 'id' => basename($path), 'metadata' => ['business_public_id' => $this->session['metadata']['business_public_id']]];
            } elseif ($path === '/v1/checkout/sessions') {
                $object = ['object' => 'list', 'data' => $this->listed, 'has_more' => $this->hasMore, 'url' => $path];
            } else {
                $object = $this->session;
            }

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

it('expires abandoned checkout before unlocking a new review and retains evidence', function () {
    [$owner, $business, $subscription, , $change, $attempt, $session] = abandonedCapacityCheckout();
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $subscription, $change, $attempt) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('status', 'cancelled');
        expect($client->session['status'])->toBe('expired')->and($attempt->fresh()->status)->toBe('expired')
            ->and($subscription->fresh()->status->value)->toBe('trialing')->and($subscription->fresh()->capacity_snapshot)->toBeNull();
        expect(DB::table('audit_events')->where('action', 'subscription.checkout.closed')->where('actor_user_id', $owner->id)->exists())->toBeTrue();
        $writes = count($client->requests);
        $this->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertOk();
        expect(count($client->requests))->toBe($writes);
        $this->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 1, 'staff' => 5])->assertOk()->assertJsonPath('quote.total_minor', 6500);
        expect(BillingCheckoutAttempt::count())->toBe(1);
    });
});

it('shows useful errors without releasing an uncertain checkout on provider failure', function (string $failure) {
    [$owner, $business, , , $change, $attempt, $session] = abandonedCapacityCheckout();
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $attempt, $failure) {
        $client->failure = $failure;
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])->assertStatus(503)->assertJsonPath('checkout_url', null)->assertDontSee('Private provider diagnostic');
        $this->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertStatus(503);
        expect($change->fresh()->status)->toBe('submitted')->and($attempt->fresh()->status)->toBe('pending');
        $this->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 1, 'staff' => 1])->assertConflict();
        $client->failure = null;
        $this->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('status', 'cancelled');
    });
})->with(['resource_missing', 'api_error']);

it('blocks a live checkout under test credentials and recovers after restoring the original mode', function () {
    [$owner, $business, , , $change, , $session] = abandonedCapacityCheckout('cs_live_abandoned');
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])->assertConflict()->assertJsonPath('checkout_url', null);
        expect($client->requests)->toBe([])->and($change->fresh()->status)->toBe('submitted');
        config(['billing.stripe.secret' => 'sk_live_restored']);
        $this->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('status', 'cancelled');
    });
});

it('uses Stripe expiry instead of a shorter local checkout deadline', function () {
    [$owner, $business, , , $change, $attempt, $session] = abandonedCapacityCheckout();
    $attempt->update(['expires_at' => now()->subHour()]);
    withCheckoutRecoveryStripe($session, function () use ($owner, $business, $change, $attempt, $session) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('checkout_url', 'https://checkout.stripe.com/abandoned');
        expect($attempt->fresh()->expires_at->timestamp)->toBe($session['expires_at']);
    });
});

it('does not expire or reconcile another business or mismatched checkout response', function (string $field) {
    [$owner, $business, , , $change, $attempt, $session] = abandonedCapacityCheckout();
    data_set($session, $field, 'unrelated');
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $attempt) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertConflict();
        expect(count($client->requests))->toBe(1)->and($attempt->fresh()->status)->toBe('pending')->and($change->fresh()->status)->toBe('submitted');
    });
})->with(['customer', 'client_reference_id', 'metadata.business_public_id', 'metadata.billing_checkout_attempt_id', 'metadata.plan_price_id']);

it('recovers a lost Checkout create response before safely closing it', function (bool $exists) {
    [$owner, $business, , , $change, $attempt, $session] = abandonedCapacityCheckout('cs_live_preparing');
    config(['billing.stripe.secret' => 'sk_live_original']);
    $attempt->update(['provider_transaction_id' => 'pending_capacity_'.$change->public_id, 'provider_checkout_url' => null, 'capacity_quote' => [...$attempt->capacity_quote, 'stripe_mode' => 'live']]);
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $attempt, $session, $exists) {
        $client->listed = $exists ? [$session] : [];
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('status', 'cancelled');
        expect($attempt->fresh()->status)->toBe('expired')->and(BillingCheckoutAttempt::count())->toBe(1);
        expect(collect($client->requests)->contains(fn ($r) => $r['method'] === 'post' && $r['path'] === '/v1/checkout/sessions'))->toBeFalse();
    });
})->with([true, false]);

it('does not abandon an incomplete provider listing or an unknown create in another mode', function (string $case) {
    [$owner, $business, , , $change, $attempt, $session] = abandonedCapacityCheckout('cs_live_preparing');
    config(['billing.stripe.secret' => 'sk_live_original']);
    $attempt->update(['provider_transaction_id' => 'pending_capacity_'.$change->public_id, 'provider_checkout_url' => null, 'capacity_quote' => [...$attempt->capacity_quote, 'stripe_mode' => 'live']]);
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $attempt, $case) {
        $client->hasMore = $case === 'listing';
        if ($case === 'mode') {
            config(['billing.stripe.secret' => 'sk_test_other']);
        }
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertConflict();
        expect($attempt->fresh()->status)->toBe('pending')->and($change->fresh()->status)->toBe('submitted');
    });
})->with(['listing', 'mode']);

it('reconciles a payment which completes while restart is expiring checkout', function (bool $race) {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout();
    $session['subscription'] = ['object' => 'subscription', 'id' => 'sub_recovered_checkout', 'customer' => $subscription->provider_customer_id,
        'status' => 'active', 'metadata' => $session['metadata'], 'items' => ['data' => capacityProvider($card, 3, 10)], 'latest_invoice' => null];
    if (! $race) {
        $session['status'] = 'complete';
    }
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $subscription, $change, $attempt, $race) {
        $client->raceCompletion = $race;
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('status', 'applied');
        expect($subscription->fresh()->status->value)->toBe('active')->and($subscription->fresh()->capacity_snapshot['total_minor'])->toBe(13300)
            ->and($attempt->fresh()->status)->toBe('confirmed')->and(BillingCheckoutAttempt::count())->toBe(1);
    });
})->with([true, false]);

it('protects checkout restart from staff and cross-business access', function () {
    [$owner, $business, , , $change] = abandonedCapacityCheckout();
    [$other, $otherBusiness] = createTenantMembership();
    activateTestSubscription($otherBusiness);
    $this->actingAs($other)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertForbidden();
    $this->postJson(route('business.billing.capacity.restart', $otherBusiness), ['change_id' => $change->public_id])->assertNotFound();
    [$staff, $staffBusiness] = createTenantMembership(StarterRole::BarberStylist);
    activateTestSubscription($staffBusiness);
    $this->actingAs($staff)->postJson(route('business.billing.capacity.restart', $staffBusiness), ['change_id' => $change->public_id])->assertForbidden();
});

it('recovers a confirmed claim even if the response failed before creating its attempt', function () {
    [$owner, $business] = createTenantMembership();
    capacityRate();
    $subscription = activateTestSubscription($business);
    $subscription->update(['status' => 'trialing', 'provider_subscription_id' => null]);
    $manager = app(CapacityBillingManager::class);
    $change = $manager->review($business, $owner, 'US', 'monthly', 3, 10);
    $manager->claim($business, $owner, $change);
    $this->mock(SubscriptionProvider::class)->shouldReceive('createCheckout')->once()->andReturn(['url' => 'https://checkout.stripe.com/gap', 'provider_session_id' => 'cs_test_gap']);
    $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('checkout_url', 'https://checkout.stripe.com/gap');
    expect(BillingCheckoutAttempt::count())->toBe(1);
    $this->get(route('business.billing.show', $business))->assertOk()->assertDontSee('stripe_credential_fingerprint')->assertDontSee(hash('sha256', config('billing.stripe.secret')));
});

it('retains a newly bound customer when checkout creation times out', function () {
    [$owner, $business] = createTenantMembership();
    capacityRate();
    $subscription = activateTestSubscription($business);
    $subscription->update(['status' => 'trialing', 'provider_subscription_id' => null, 'provider_customer_id' => null]);
    $change = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 1, 2);
    $this->mock(SubscriptionProvider::class)->shouldReceive('createCheckout')->once()->andReturnUsing(function () use ($subscription) {
        $subscription->update(['provider_customer_id' => 'cus_created_before_timeout']);
        throw new HttpException(503, 'Provider timeout');
    });
    $this->actingAs($owner)->postJson(route('business.billing.capacity.confirm', $business), ['change_id' => $change->public_id])->assertStatus(503);
    expect($subscription->fresh()->provider_customer_id)->toBe('cus_created_before_timeout')->and(BillingCheckoutAttempt::count())->toBe(1);
    expect(BillingCheckoutAttempt::first()->capacity_quote['capacity_change_id'])->toBe($change->public_id);
});

it('does not let an old expiration unlock a newer checkout with the same pricing', function () {
    [$owner, $business, , , $change, $attempt, $session] = abandonedCapacityCheckout();
    withCheckoutRecoveryStripe($session, function () use ($owner, $business, $change) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertOk();
    });
    $new = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 3, 10);
    $new->update(['status' => 'submitted', 'submitted_at' => now()]);
    app(StripeWebhookProcessor::class)->receiveVerified(['id' => 'evt_old_expiry_after_restart', 'type' => 'checkout.session.expired', 'created' => now()->timestamp, 'data' => ['object' => [...$session, 'status' => 'expired']]]);
    expect($new->fresh()->status)->toBe('submitted')->and($attempt->fresh()->status)->toBe('expired');
});

it('keeps checkout recovery available while new purchases and webhook readiness are paused', function () {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout();
    config(['capacity-billing.enabled' => false, 'billing.stripe.webhook_secret' => '']);
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', 'submitted');
        $this->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', 'cancelled');
        expect(collect($client->requests)->filter(fn ($request) => str_ends_with($request['path'], '/expire')))->toHaveCount(1);
    });
    expect($subscription->fresh()->status->value)->toBe('trialing')->and($attempt->fresh()->status)->toBe('expired');
});

it('retires a stale test checkout on an unpaid trial after switching back to live', function (string $action, bool $legacy) {
    [$owner, $business, $subscription, , $change, $attempt, $session] = abandonedCapacityCheckout();
    $customer = $subscription->provider_customer_id;
    if ($legacy) {
        $attempt->update(['capacity_quote' => $change->quote]);
    }
    config(['billing.stripe.secret' => 'sk_live_restored']);
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $action) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.'.$action, $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', $action === 'restart' ? 'cancelled' : 'expired')->assertJsonPath('checkout_url', null);
        expect($client->requests)->toBe([]);
        $this->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 4, 'staff' => 20])
            ->assertOk()->assertJsonPath('quote.total_minor', 19200);
    });
    expect($subscription->fresh()->provider_customer_id)->toBeNull()->and($subscription->fresh()->status->value)->toBe('trialing')
        ->and($attempt->fresh()->status)->toBe('superseded')->and($attempt->fresh()->capacity_quote['retired_test_customer_id'])->toBe($customer);
    expect(DB::table('audit_events')->where('action', 'subscription.checkout.test_retired')->exists())->toBeTrue();
})->with([['restart', false], ['status', false], ['restart', true]]);

it('starts a fresh live checkout with a live customer after retiring test setup', function () {
    [$owner, $business, $subscription, , $change, $attempt, $session] = abandonedCapacityCheckout();
    $oldCustomer = $subscription->provider_customer_id;
    config(['billing.stripe.secret' => 'sk_live_restored']);
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $oldCustomer) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])->assertOk();
        $review = $this->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 4, 'staff' => 20])->assertOk();
        $this->postJson(route('business.billing.capacity.confirm', $business), ['change_id' => $review->json('change_id')])
            ->assertCreated()->assertJsonPath('url', 'https://checkout.stripe.com/recovered');
        $writes = collect($client->requests)->where('method', 'post');
        expect($writes->where('path', '/v1/customers'))->toHaveCount(1);
        $checkout = $writes->firstWhere('path', '/v1/checkout/sessions');
        expect($checkout['params']['customer'])->toBe('cus_live_recovered')->not->toBe($oldCustomer);
        $this->postJson(route('business.billing.capacity.confirm', $business), ['change_id' => $review->json('change_id')])->assertOk();
        expect(collect($client->requests)->where('method', 'post')->where('path', '/v1/checkout/sessions'))->toHaveCount(1);
    });
    expect($subscription->fresh()->provider_customer_id)->toBe('cus_live_recovered')
        ->and($subscription->fresh()->status->value)->toBe('trialing')->and(BillingCheckoutAttempt::count())->toBe(2);
    $this->get(route('business.billing.show', $business))->assertDontSee('retired_test_customer_id');
    app(StripeWebhookProcessor::class)->receiveVerified(['id' => 'evt_retired_test_completion', 'type' => 'checkout.session.completed', 'created' => now()->timestamp, 'data' => ['object' => [...$session, 'status' => 'complete', 'subscription' => 'sub_test_late']]]);
    expect($subscription->fresh()->provider_customer_id)->toBe('cus_live_recovered')->and($subscription->fresh()->provider_subscription_id)->toBeNull();
});

it('never discards commercial history or another pending checkout to retire test setup', function (string $history, bool $preparing) {
    [$owner, $business, $subscription, , $change, $attempt, $session] = abandonedCapacityCheckout();
    if ($preparing) {
        $attempt->update(['provider_transaction_id' => 'pending_capacity_'.$change->public_id]);
    }
    $customer = $subscription->provider_customer_id;
    if ($history === 'invoice') {
        $subscription->invoices()->create(['business_id' => $business->id, 'provider' => 'stripe', 'provider_invoice_id' => 'in_retained', 'status' => 'paid', 'currency' => 'USD', 'subtotal_minor' => 2900, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 2900, 'amount_due_minor' => 2900, 'amount_paid_minor' => 2900]);
    } elseif ($history === 'other_attempt') {
        $other = $attempt->replicate(['public_id']);
        $other->provider_transaction_id = 'cs_live_unresolved';
        $other->capacity_quote = [...$attempt->capacity_quote, 'capacity_change_id' => (string) str()->ulid()];
        $other->save();
    } else {
        $subscription->update(match ($history) {
            'paid' => ['status' => 'active', 'provider_subscription_id' => 'sub_retained'],
            'capacity' => ['capacity_snapshot' => $change->quote],
            'card' => ['payment_method_last_four' => '4242'],
        });
    }
    config(['billing.stripe.secret' => 'sk_live_restored']);
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])
            ->assertConflict()->assertJsonPath('code', 'billing_review_required')->assertJsonPath('support_required', true)
            ->assertDontSee('payment environment')->assertDontSee('Restore');
        expect($client->requests)->toBe([]);
    });
    expect($subscription->fresh()->provider_customer_id)->toBe($customer)->and($attempt->fresh()->status)->toBe('pending')->and($change->fresh()->status)->toBe('submitted');
})->with(['paid', 'invoice', 'capacity', 'card', 'other_attempt'])->with([false, true]);

it('keeps status polling from consuming restart or review limits and returns a usable retry delay', function () {
    [$owner, $business, , , $change, , $session] = abandonedCapacityCheckout('cs_live_abandoned');
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change) {
        $this->actingAs($owner);
        $payload = ['change_id' => $change->public_id];
        for ($i = 0; $i < 30; $i++) {
            $this->postJson(route('business.billing.capacity.status', $business), $payload)->assertConflict();
        }
        $limited = $this->postJson(route('business.billing.capacity.status', $business), $payload)
            ->assertStatus(429)->assertJsonPath('code', 'billing_retry_later')->assertHeader('Retry-After');
        expect($limited->json('retry_after'))->toBeGreaterThan(0)->and($limited->json('retry_after'))->toBeLessThanOrEqual(60);
        config(['billing.stripe.secret' => 'sk_live_restored']);
        $this->postJson(route('business.billing.capacity.restart', $business), $payload)->assertOk()->assertJsonPath('status', 'cancelled');
        $this->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 1, 'staff' => 1])->assertOk();
    });
});

it('gives operators safe checkout and cached configuration diagnostics without exposing credentials', function () {
    [$owner, $business, , , $change, $attempt] = abandonedCapacityCheckout('cs_live_original');
    $file = tempnam(sys_get_temp_dir(), 'billing-diagnostic-');
    file_put_contents($file, "STRIPE_SECRET=sk_live_fake_private_for_diagnostic\nUNRELATED_SECRET=do_not_print\n");
    $environmentPath = app()->environmentPath();
    $environmentFile = app()->environmentFile();
    try {
        app()->useEnvironmentPath(dirname($file))->loadEnvironmentFrom(basename($file));
        Artisan::call('billing:inspect-checkout', ['business' => $business->public_id]);
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        expect($report['configured_mode'])->toBe('test')->and($report['checkout_mode'])->toBe('live')
            ->and($report['environment_file_mode'])->toBe('live')->and($report['configured_secret_matches_environment_file'])->toBeFalse()
            ->and($report['reference'])->toBe($change->public_id)->and($output)->not->toContain('sk_live_', 'fake_private', 'do_not_print');
        expect($attempt->fresh()->status)->toBe('pending')->and($change->fresh()->status)->toBe('submitted');
    } finally {
        app()->useEnvironmentPath($environmentPath)->loadEnvironmentFrom($environmentFile);
        unlink($file);
    }
});

it('inspects the exact support reference copied from billing without requiring a business ID', function (bool $lowercase) {
    [, $business, $subscription, , $change, $attempt] = abandonedCapacityCheckout('cs_live_original');
    $reference = '01M5SXKT2CT8QQVVFCTSY1QBQH';
    $change->update(['public_id' => $reference]);
    $attempt->update(['capacity_quote' => [...$attempt->capacity_quote, 'capacity_change_id' => $reference]]);
    $before = [$change->fresh()->getAttributes(), $attempt->fresh()->getAttributes(), $subscription->fresh()->getAttributes()];
    withCheckoutRecoveryStripe([], function ($client) use ($reference, $lowercase, $business, $attempt) {
        $exit = Artisan::call('billing:inspect-checkout', ['business' => $lowercase ? '  '.strtolower($reference).'  ' : $reference]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        expect($exit)->toBe(0)->and($report['business'])->toBe($business->public_id)
            ->and($report['reference'])->toBe($reference)->and($report['matched_by'])->toBe('support_reference')
            ->and($report['request_status'])->toBe('submitted')->and($report['checkout_mode'])->toBe('live')
            ->and(Artisan::output())->not->toContain($attempt->provider_transaction_id, 'stripe_credential_fingerprint')
            ->and($client->requests)->toBe([]);
    });
    expect([$change->fresh()->getAttributes(), $attempt->fresh()->getAttributes(), $subscription->fresh()->getAttributes()])->toBe($before);
})->with([false, true]);

it('keeps a historical support reference separate from a newer checkout for the same business', function () {
    [, $business, , , $old, $oldAttempt] = abandonedCapacityCheckout('cs_live_original');
    $old->update(['status' => 'cancelled']);
    $oldAttempt->update(['status' => 'expired']);
    $new = $old->replicate(['public_id']);
    $new->status = 'submitted';
    $new->submitted_at = now()->addSecond();
    $new->save();
    $newAttempt = $oldAttempt->replicate(['public_id']);
    $newAttempt->status = 'pending';
    $newAttempt->provider_transaction_id = 'cs_test_newer';
    $newAttempt->capacity_quote = [...$oldAttempt->capacity_quote, 'capacity_change_id' => $new->public_id];
    $newAttempt->save();
    expect(Artisan::call('billing:inspect-checkout', ['business' => $old->public_id]))->toBe(0);
    $historical = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($historical['reference'])->toBe($old->public_id)->and($historical['request_status'])->toBe('cancelled')
        ->and($historical['attempt_status'])->toBe('expired')->and($historical['checkout_mode'])->toBe('live')
        ->and($historical['next_step'])->toContain('no longer pending');
    expect(Artisan::call('billing:inspect-checkout', ['business' => $business->public_id]))->toBe(0);
    $current = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($current['reference'])->toBe($new->public_id)->and($current['matched_by'])->toBe('business')
        ->and($current['attempt_status'])->toBe('pending')->and($current['checkout_mode'])->toBe('test');
});

it('explains how to find the correct checkout identifier or deployment when no match exists', function () {
    $this->artisan('billing:inspect-checkout', ['business' => (string) str()->ulid()])
        ->expectsOutputToContain('No business or checkout Support reference matched')
        ->expectsOutputToContain('/businesses/<ID>/billing')
        ->expectsOutputToContain('current release and database')->assertFailed();
});

it('reports no pending checkout without giving instructions for a nonexistent session', function () {
    [, $business] = createTenantMembership();
    expect(Artisan::call('billing:inspect-checkout', ['business' => $business->public_id]))->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($report['matched_by'])->toBe('business')->and($report['reference'])->toBeNull()
        ->and($report['attempt_status'])->toBeNull()->and($report['next_step'])->toContain('No submitted checkout');
});

it('keeps a declined first invoice retryable in its original monthly or annual checkout', function (string $interval) {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout('cs_test_declined', $interval);
    $invoice = ['object' => 'invoice', 'id' => 'in_initial_declined', 'customer' => $subscription->provider_customer_id,
        'subscription' => 'sub_initial_declined', 'status' => 'open', 'billing_reason' => 'subscription_create',
        'currency' => 'usd', 'subtotal' => $change->quote['total_minor'], 'total' => $change->quote['total_minor'],
        'amount_due' => $change->quote['total_minor'], 'amount_paid' => 0, 'metadata' => $session['metadata']];
    app(StripeWebhookProcessor::class)->receiveVerified(['id' => 'evt_initial_decline', 'type' => 'invoice.payment_failed', 'created' => now()->timestamp, 'data' => ['object' => $invoice]]);
    expect($attempt->fresh()->status)->toBe('pending')->and($attempt->fresh()->last_error)->toBe('initial_payment_failed');
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', 'submitted')->assertJsonPath('checkout_url', 'https://checkout.stripe.com/abandoned');
        $this->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 1, 'staff' => 1])->assertConflict();
        expect(collect($client->requests)->where('method', 'post'))->toHaveCount(0);
    });
    expect($subscription->fresh()->status->value)->toBe('trialing')->and($subscription->fresh()->capacity_snapshot)->toBeNull();
})->with(['monthly', 'annual']);

it('releases only a provider-expired unpaid first subscription so monthly and annual checkout can restart', function (string $interval) {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout('cs_test_incomplete', $interval);
    $providerId = 'sub_initial_expired';
    $subscription->update(['provider_subscription_id' => $providerId]);
    $attempt->update(['provider_subscription_id' => $providerId, 'status' => 'processing']);
    $object = ['object' => 'subscription', 'id' => $providerId, 'customer' => $subscription->provider_customer_id,
        'status' => 'incomplete_expired', 'metadata' => $session['metadata']];
    $event = ['id' => 'evt_initial_expired', 'type' => 'customer.subscription.updated', 'created' => now()->timestamp, 'data' => ['object' => $object]];
    app(StripeWebhookProcessor::class)->receiveVerified($event);
    app(StripeWebhookProcessor::class)->receiveVerified($event);
    expect($subscription->fresh()->provider_subscription_id)->toBeNull()->and($subscription->fresh()->status->value)->toBe('trialing')
        ->and($change->fresh()->status)->toBe('expired')->and($attempt->fresh()->status)->toBe('failed')
        ->and($attempt->fresh()->provider_subscription_id)->toBe($providerId);
    $this->actingAs($owner)->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => $interval, 'locations' => 1, 'staff' => 5])
        ->assertOk()->assertJsonPath('kind', 'checkout')->assertJsonPath('quote.total_minor', $interval === 'annual' ? 65000 : 6500);
})->with(['monthly', 'annual']);

it('converges monthly and annual payment success after a decline without duplicating subscription or invoice', function (string $interval) {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout('cs_test_retry_paid', $interval);
    $total = $change->quote['total_minor'];
    $invoice = ['object' => 'invoice', 'id' => 'in_retry_paid', 'customer' => $subscription->provider_customer_id,
        'subscription' => 'sub_retry_paid', 'status' => 'open', 'billing_reason' => 'subscription_create', 'currency' => 'usd',
        'subtotal' => $total, 'total' => $total, 'amount_due' => $total, 'amount_paid' => 0,
        'metadata' => $session['metadata'], 'created' => now()->timestamp];
    $failedEvent = ['id' => 'evt_retry_decline', 'type' => 'invoice.payment_failed', 'created' => now()->timestamp - 1, 'data' => ['object' => $invoice]];
    app(StripeWebhookProcessor::class)->receiveVerified($failedEvent);
    $paid = [...$invoice, 'status' => 'paid', 'amount_paid' => $total, 'amount_remaining' => 0];
    $provider = ['object' => 'subscription', 'id' => 'sub_retry_paid', 'customer' => $subscription->provider_customer_id,
        'status' => 'active', 'metadata' => $session['metadata'], 'items' => ['data' => capacityProvider($card, 3, 10)], 'latest_invoice' => $paid];
    $session = [...$session, 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => $provider];
    withCheckoutRecoveryStripe($session, function () use ($owner, $business, $change) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', 'applied');
        $this->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])->assertOk()->assertJsonPath('status', 'applied');
    });
    app(StripeWebhookProcessor::class)->receiveVerified(['id' => 'evt_retry_success', 'type' => 'invoice.paid', 'created' => now()->timestamp, 'data' => ['object' => $paid]]);
    app(StripeWebhookProcessor::class)->receiveVerified($failedEvent);
    expect($subscription->fresh()->status->value)->toBe('active')->and($subscription->fresh()->billing_interval->value)->toBe($interval)
        ->and($subscription->fresh()->capacity_snapshot['total_minor'])->toBe($interval === 'annual' ? 133000 : 13300)
        ->and($subscription->invoices()->count())->toBe(1)->and($subscription->invoices()->first()->status)->toBe('paid')
        ->and($attempt->fresh()->status)->toBe('confirmed')->and(BillingCheckoutAttempt::count())->toBe(1);
})->with(['monthly', 'annual']);

it('restarts unfinished same-mode test preparation and opens the newly reviewed monthly or annual checkout', function (string $interval, bool $changedCredentials) {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout('cs_test_preparing', $interval);
    $customer = $subscription->provider_customer_id;
    $attempt->update(['provider_transaction_id' => 'pending_capacity_'.$change->public_id, 'provider_checkout_url' => null,
        'expires_at' => now()->subHour(), 'created_at' => now()->subDays(2),
        'capacity_quote' => [...$attempt->capacity_quote, 'stripe_mode' => 'test', 'stripe_credential_fingerprint' => hash('sha256', 'sk_test_original')]]);
    config(['billing.stripe.secret' => $changedCredentials ? 'sk_test_another_sandbox' : 'sk_test_original']);
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $interval) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('checkout_url', null);
        expect($client->requests)->toBe([]);
        $review = $this->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => $interval, 'locations' => 1, 'staff' => 5])
            ->assertOk()->assertJsonPath('quote.total_minor', $interval === 'annual' ? 65000 : 6500);
        $this->postJson(route('business.billing.capacity.confirm', $business), ['change_id' => $review->json('change_id')])
            ->assertCreated()->assertJsonPath('url', 'https://checkout.stripe.com/recovered');
        $this->postJson(route('business.billing.capacity.confirm', $business), ['change_id' => $review->json('change_id')])->assertOk();
        expect(collect($client->requests)->where('path', '/v1/checkout/sessions')->where('method', 'post'))->toHaveCount(1);
    });
    expect($attempt->fresh()->status)->toBe('superseded')->and($attempt->fresh()->capacity_quote['retired_test_customer_id'])->toBe($customer)
        ->and($subscription->fresh()->provider_customer_id)->toBe('cus_live_recovered')
        ->and($subscription->fresh()->status->value)->toBe('trialing')->and(BillingCheckoutAttempt::count())->toBe(2);
})->with([['monthly', false], ['monthly', true], ['annual', false], ['annual', true]]);

it('offers a usable restart instead of a support dead end when a test preparation check cannot be verified', function () {
    [$owner, $business, , , $change, $attempt, $session] = abandonedCapacityCheckout();
    $attempt->update(['provider_transaction_id' => 'pending_capacity_'.$change->public_id, 'provider_checkout_url' => null,
        'expires_at' => now()->subHour(), 'capacity_quote' => [...$attempt->capacity_quote, 'stripe_mode' => 'test']]);
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change) {
        $client->session['metadata']['business_public_id'] = 'foreign_business';
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
            ->assertConflict()->assertJsonPath('code', 'billing_restart_available')->assertJsonPath('can_restart', true)
            ->assertJsonPath('support_required', false)->assertJsonPath('retry_after', 0);
        $this->postJson(route('business.billing.capacity.restart', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', 'cancelled');
    });
});

it('resumes the original paid change after a lost update response without a second payment key', function (string $interval, string $kind) {
    [$owner, $business, $subscription, $card] = capacityFixture(3, 20, $interval);
    $locations = $kind === 'renewal' ? 1 : 4;
    $staff = $kind === 'renewal' ? 5 : 25;
    $provider = ['object' => 'subscription', 'id' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id,
        'status' => 'active', 'cancel_at_period_end' => false, 'pending_update' => null, 'schedule' => null, 'latest_invoice' => null,
        'items' => ['object' => 'list', 'data' => capacityProvider($card, 3, 20)], 'discounts' => []];
    $client = new class($provider, capacityProvider($card, $locations, $staff)) implements ClientInterface
    {
        public array $requests = [];

        public bool $failUpdate = true;

        private array $schedule = [];

        public function __construct(public array $provider, private array $target) {}

        public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $path = parse_url($url, PHP_URL_PATH);
            $this->requests[] = compact('method', 'path', 'headers', 'params');
            if ($path === '/v1/invoices/create_preview') {
                $object = ['object' => 'invoice', 'currency' => 'usd', 'amount_due' => 500,
                    'lines' => ['data' => [['amount' => 500, 'proration' => true]]]];
            } elseif ($path === '/v1/subscription_schedules') {
                $this->provider['schedule'] = $this->schedule = ['object' => 'subscription_schedule', 'id' => 'sub_sched_resume',
                    'subscription' => $this->provider['id'], 'customer' => $this->provider['customer'], 'status' => 'active', 'phases' => []];
                $object = $this->schedule;
            } elseif ($method === 'post') {
                if ($this->failUpdate) {
                    return [json_encode(['error' => ['type' => 'api_error', 'message' => 'Lost update response']]), 503, []];
                }
                if (str_starts_with($path, '/v1/subscription_schedules/')) {
                    $this->schedule['phases'] = $params['phases'];
                    $this->provider['schedule'] = $this->schedule;
                    $object = $this->schedule;
                } else {
                    $this->provider['items']['data'] = $this->target;
                    $object = $this->provider;
                }
            } else {
                $object = $this->provider;
            }

            return [json_encode($object), 200, []];
        }
    };
    ApiRequestor::setHttpClient($client);
    try {
        $review = $this->actingAs($owner)->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => $interval, 'locations' => $locations, 'staff' => $staff])->assertOk();
        $change = BillingCapacityChange::where('public_id', $review->json('change_id'))->firstOrFail();
        $this->postJson(route('business.billing.capacity.confirm', $business), ['change_id' => $change->public_id])->assertStatus(503);
        expect($change->fresh()->status)->toBe('submitted')->and($subscription->fresh()->capacity_snapshot['staff'])->toBe(20);
        $client->failUpdate = false;
        $this->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])->assertOk()
            ->assertJsonPath('status', $kind === 'renewal' ? 'scheduled' : 'applied');
        $writes = collect($client->requests)->where('method', 'post')->filter(fn ($r) => ! str_contains($r['path'], 'preview'));
        foreach ($writes->groupBy('path') as $requests) {
            $keys = $requests->map(fn ($r) => collect($r['headers'])->first(fn ($h) => str_starts_with($h, 'Idempotency-Key:')))->unique();
            expect($keys)->toHaveCount(1)->and($keys->first())->toContain($change->public_id);
            expect($requests->pluck('params')->unique())->toHaveCount(1);
        }
        $count = count($client->requests);
        $this->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])->assertOk();
        expect(count($client->requests))->toBe($count)->and(BillingCapacityChange::count())->toBe(1);
        expect($subscription->fresh()->capacity_snapshot['staff'])->toBe($kind === 'renewal' ? 20 : $staff);
    } finally {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }
})->with(['monthly', 'annual'])->with(['immediate', 'renewal']);

it('does not replay an old change or a payment already awaiting completion', function (string $case) {
    [$owner, $business, $subscription, $card] = capacityFixture(3, 20);
    $quote = app(CapacityPricingCatalog::class)->quote('US', 'monthly', 4, 25, $card);
    $quote['proration_date'] = now()->timestamp;
    $change = BillingCapacityChange::create(['business_id' => $business->id, 'business_subscription_id' => $subscription->id,
        'actor_user_id' => $owner->id, 'subscription_version' => $subscription->version, 'kind' => $case === 'renewal_date' ? 'renewal' : 'immediate',
        'status' => 'submitted', 'submitted_at' => $case === 'old' ? now()->subHours(24) : now(), 'effective_at' => now()->addDay(),
        'quote' => $quote, 'expires_at' => now()->addMinutes(10)]);
    $object = ['object' => 'subscription', 'id' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id,
        'status' => 'active', 'cancel_at_period_end' => false, 'pending_update' => $case === 'pending_payment' ? ['expires_at' => now()->addDay()->timestamp] : null,
        'schedule' => null, 'items' => ['object' => 'list', 'data' => capacityProvider($card, 3, 20)], 'discounts' => []];
    if ($case === 'pending_payment') {
        $subscription->invoices()->create(['business_id' => $business->id, 'provider' => 'stripe', 'provider_invoice_id' => 'in_pending_expansion',
            'status' => 'open', 'currency' => 'USD', 'subtotal_minor' => 500, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 500,
            'amount_due_minor' => 500, 'amount_paid_minor' => 0, 'amount_remaining_minor' => 500]);
    }
    withCapacityStripeTransport($object, function ($client) use ($owner, $business, $change, $subscription, $case) {
        if ($case === 'pending_payment') {
            $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
                ->assertConflict()->assertJsonPath('code', 'billing_payment_required')->assertJsonPath('support_required', false)
                ->assertJsonPath('payment_required', true)->assertHeader('Retry-After', '15');
        } else {
            try {
                app(CapacityStripeGateway::class)->submit($subscription, $change, resume: true);
                $this->fail('Unsafe replay must be rejected.');
            } catch (HttpException $exception) {
                expect($exception->getStatusCode())->toBe(409);
            }
        }
        expect(collect($client->requests)->where('method', 'post'))->toHaveCount(0);
    });
    expect($change->fresh()->status)->toBe('submitted')->and($subscription->fresh()->capacity_snapshot['staff'])->toBe(20);
})->with(['old', 'renewal_date', 'pending_payment']);

it('never detaches paid or unrelated subscriptions on an initial-checkout expiration', function (string $case) {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout();
    $subscription->update(['provider_subscription_id' => $case === 'different_binding' ? 'sub_current_paid' : 'sub_initial_expired']);
    $attempt->update(['provider_subscription_id' => 'sub_initial_expired', 'status' => 'processing']);
    if ($case === 'paid_status') {
        $subscription->update(['status' => 'active', 'capacity_snapshot' => $change->quote]);
        $attempt->update(['status' => 'confirmed', 'confirmed_at' => now()]);
    } elseif ($case === 'paid_invoice') {
        $subscription->invoices()->create(['business_id' => $business->id, 'provider' => 'stripe', 'provider_invoice_id' => 'in_expiration_paid_guard',
            'status' => 'paid', 'currency' => 'USD', 'subtotal_minor' => 13300, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 13300,
            'amount_due_minor' => 13300, 'amount_paid_minor' => 13300]);
    } elseif ($case === 'newer_snapshot') {
        $subscription->update(['provider_state_at' => now()->addMinute()]);
    }
    $binding = $subscription->fresh()->provider_subscription_id;
    app(StripeWebhookProcessor::class)->synchronizeSubscriptionSnapshot(['object' => 'subscription', 'id' => 'sub_initial_expired',
        'customer' => $subscription->provider_customer_id, 'status' => 'incomplete_expired', 'metadata' => $session['metadata']], now());
    expect($subscription->fresh()->provider_subscription_id)->toBe($binding)->and($change->fresh()->status)->toBe('submitted')
        ->and(DB::table('audit_events')->where('action', 'subscription.checkout.unpaid_expired')->exists())->toBeFalse();
    if ($case === 'paid_status') {
        expect($attempt->fresh()->status)->toBe('confirmed')->and($subscription->fresh()->status->value)->toBe('active');
    }
})->with(['paid_status', 'paid_invoice', 'different_binding', 'newer_snapshot']);

it('finishes payment with fractional API observation time and ignores an equal-second stale event', function (string $interval) {
    Carbon::setTestNow(now()->setMicrosecond(456789));
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout('cs_test_real_clock', $interval);
    $provider = ['object' => 'subscription', 'id' => 'sub_real_clock', 'customer' => $subscription->provider_customer_id,
        'status' => 'active', 'metadata' => $session['metadata'], 'items' => ['data' => capacityProvider($card, 3, 10)], 'latest_invoice' => null];
    $session = [...$session, 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => $provider];
    withCheckoutRecoveryStripe($session, function () use ($owner, $business, $change) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', 'applied');
    });
    expect($subscription->fresh()->capacity_snapshot['staff'])->toBe(10)->and($attempt->fresh()->status)->toBe('confirmed');
    $version = $subscription->fresh()->version;
    app(StripeWebhookProcessor::class)->receiveVerified(['id' => 'evt_stale_same_second', 'type' => 'customer.subscription.updated',
        'created' => now()->timestamp, 'data' => ['object' => [...$provider, 'items' => ['data' => capacityProvider($card, 1, 1)]]]]);
    expect($subscription->fresh()->capacity_snapshot['staff'])->toBe(10)->and($subscription->fresh()->version)->toBe($version);
})->with(['monthly', 'annual']);

it('repairs a legacy prematurely failed card attempt from its verified original session', function (string $state) {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout();
    $attempt->update(['status' => 'failed', 'last_error' => 'initial_payment_failed']);
    $session['status'] = $state;
    if ($state === 'complete') {
        $session['subscription'] = ['object' => 'subscription', 'id' => 'sub_legacy_retry', 'customer' => $subscription->provider_customer_id,
            'status' => 'active', 'metadata' => $session['metadata'], 'items' => ['data' => capacityProvider($card, 3, 10)]];
    }
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $state) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', match ($state) {
                'complete' => 'applied', 'expired' => 'expired', default => 'submitted'
            });
        expect(collect($client->requests)->where('method', 'post'))->toHaveCount(0);
    });
    expect($attempt->fresh()->status)->toBe(match ($state) {
        'complete' => 'confirmed', 'expired' => 'expired', default => 'pending'
    })
        ->and(BillingCheckoutAttempt::count())->toBe(1);
})->with(['open', 'complete', 'expired']);

it('offers only a verified existing invoice after completed checkout still needs payment', function (string $case) {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout();
    $invoice = ['object' => 'invoice', 'id' => 'in_action_required', 'customer' => $subscription->provider_customer_id,
        'subscription' => 'sub_action_required', 'status' => 'open', 'currency' => 'usd', 'subtotal' => $change->quote['total_minor'],
        'total' => $change->quote['total_minor'], 'amount_due' => $change->quote['total_minor'], 'amount_paid' => 0,
        'hosted_invoice_url' => 'https://invoice.stripe.com/i/retry', 'metadata' => $session['metadata']];
    if ($case === 'wrong_customer') {
        $invoice['customer'] = 'cus_foreign';
    }
    if ($case === 'wrong_subscription') {
        $invoice['subscription'] = 'sub_foreign';
    }
    if ($case === 'wrong_amount') {
        $invoice['subtotal']++;
    }
    if ($case === 'unsafe_url') {
        $invoice['hosted_invoice_url'] = 'https://untrusted.example.test/pay';
    }
    $session = [...$session, 'status' => 'complete', 'subscription' => ['object' => 'subscription', 'id' => 'sub_action_required',
        'customer' => $subscription->provider_customer_id, 'status' => 'incomplete', 'metadata' => $session['metadata'], 'latest_invoice' => $invoice]];
    withCheckoutRecoveryStripe($session, function ($client) use ($owner, $business, $change, $case) {
        $this->actingAs($owner)->postJson(route('business.billing.capacity.status', $business), ['change_id' => $change->public_id])
            ->assertOk()->assertJsonPath('status', 'submitted')->assertJsonPath('checkout_url', $case === 'owned' ? 'https://invoice.stripe.com/i/retry' : null);
        expect(collect($client->requests)->where('method', 'post'))->toHaveCount(0);
    });
    expect($attempt->fresh()->status)->toBe('processing')->and(BillingCheckoutAttempt::count())->toBe(1);
})->with(['owned', 'wrong_customer', 'wrong_subscription', 'wrong_amount', 'unsafe_url']);

it('does not release a delayed payment failure for a duplicate new subscription', function () {
    [$owner, $business, $subscription, $card, $change, $attempt, $session] = abandonedCapacityCheckout();
    $session = [...$session, 'status' => 'complete', 'subscription' => 'sub_delayed_failed'];
    $processor = app(StripeWebhookProcessor::class);
    $processor->receiveVerified(['id' => 'evt_delayed_completed', 'type' => 'checkout.session.completed', 'created' => now()->timestamp, 'data' => ['object' => $session]]);
    $processor->receiveVerified(['id' => 'evt_delayed_failed', 'type' => 'checkout.session.async_payment_failed', 'created' => now()->timestamp, 'data' => ['object' => $session]]);
    expect($attempt->fresh()->status)->toBe('processing')->and($attempt->fresh()->provider_checkout_url)->toBeNull()
        ->and($change->fresh()->status)->toBe('submitted')->and($subscription->fresh()->provider_subscription_id)->toBe('sub_delayed_failed');
    $this->actingAs($owner)->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 1, 'staff' => 1])->assertConflict();
});
