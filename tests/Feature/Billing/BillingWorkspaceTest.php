<?php

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPayment;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Services\BillingWorkspaceQuery;
use App\Domain\Billing\Services\StripeSubscriptionReconciler;
use App\Domain\Billing\Services\StripeWebhookProcessor;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(fn () => Carbon::setTestNow('2026-10-05 12:00:00'));
afterEach(fn () => Carbon::setTestNow());

function billingWorkspaceFixture(): array
{
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $plan = BillingPlan::where('code', 'pro')->firstOrFail();
    $price = BillingPlanPrice::firstOrCreate(['provider_price_id' => 'price_workspace_test'], ['billing_plan_id' => $plan->id, 'billing_interval' => BillingInterval::Monthly,
        'currency' => 'USD', 'amount_minor' => 10000, 'provider' => 'stripe',
        'catalog_managed' => true, 'is_active' => true, 'effective_from' => now()->subDay()]);
    $subscription = BusinessSubscription::create(['business_id' => $business->id, 'billing_plan_id' => $plan->id,
        'billing_plan_price_id' => $price->id, 'provider' => 'stripe', 'provider_customer_id' => 'cus_workspace_'.$business->id,
        'provider_subscription_id' => 'sub_workspace_'.$business->id, 'status' => 'active', 'restriction_level' => 'none',
        'billing_interval' => 'monthly', 'current_period_started_at' => now()->subDays(10), 'current_period_ends_at' => now()->addDays(20)]);

    return [$owner, $business, $subscription, $price];
}
function workspaceInvoice(BusinessSubscription $subscription, array $extra = []): BillingInvoice
{
    return BillingInvoice::create($extra + ['business_id' => $subscription->business_id, 'business_subscription_id' => $subscription->id,
        'provider' => 'stripe', 'provider_invoice_id' => 'in_workspace_'.str()->ulid(), 'number' => 'CD-0001', 'status' => 'paid',
        'currency' => 'USD', 'subtotal_minor' => 10000, 'tax_minor' => 825, 'total_minor' => 10825,
        'amount_due_minor' => 10825, 'amount_paid_minor' => 10825, 'issued_at' => now(),
        'hosted_url' => 'https://invoice.stripe.com/i/example', 'pdf_url' => 'https://pay.stripe.com/invoice/example/pdf',
        'line_items' => [['description' => 'Pro', 'amount' => 10000, 'period' => ['start' => now()->timestamp, 'end' => now()->addMonth()->timestamp]]]]);
}
function workspaceProviderInvoice(BusinessSubscription $subscription, array $extra = []): array
{
    return $extra + ['id' => 'in_projected', 'object' => 'invoice', 'subscription' => $subscription->provider_subscription_id,
        'customer' => $subscription->provider_customer_id, 'status' => 'open', 'currency' => 'usd', 'subtotal' => 10000,
        'total' => 10825, 'amount_due' => 10825, 'amount_paid' => 0, 'amount_remaining' => 10825,
        'payment_intent' => 'pi_projected', 'created' => now()->timestamp, 'attempt_count' => 2];
}

it('returns bounded invoice history and safe summary fields without trusting return markers', function () {
    [$owner, $business, $subscription] = billingWorkspaceFixture();
    for ($i = 0; $i < 14; $i++) {
        workspaceInvoice($subscription, ['issued_at' => now()->subDays($i)]);
    }
    StaffProfile::create(['business_id' => $business->id, 'display_name' => 'A stylist', 'status' => 'active']);
    $this->actingAs($owner)->get(route('business.billing.show', [$business, 'checkout' => 'success', 'plan_change' => 'confirmed']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Billing/Overview')
        ->where('subscription.price.amount_minor', 10000)->where('usage', fn ($usage) => $usage['staff.max'] === 1)
        ->has('invoices.data', 10)->where('invoices.total', 14)->where('checkoutStatus', null)->where('planChangeStatus', null)
        ->missing('subscription.provider_customer_id')->missing('subscription.provider_subscription_id')->missing('payments')
        ->missing('invoices.data.0.provider_invoice_id'));
    $this->actingAs($owner)->get(route('business.billing.show', [$business, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 4)->where('invoices.current_page', 2));
});

it('shows only approved plan prices and retains historical subscription prices', function () {
    [$owner,$business,$subscription] = billingWorkspaceFixture();
    $subscription->price->update(['is_active' => false]);
    $this->actingAs($owner)->get(route('business.billing.show', $business))->assertInertia(fn (Assert $page) => $page
        ->where('subscription.price.amount_minor', 10000)->where('plans.1.prices', []));
});

it('separates outstanding currencies and excludes paid invoices from amount due', function () {
    [,,$subscription] = billingWorkspaceFixture();
    workspaceInvoice($subscription, ['status' => 'open', 'amount_due_minor' => 10825, 'amount_paid_minor' => 8000]);
    workspaceInvoice($subscription, ['status' => 'open', 'currency' => 'EUR', 'amount_due_minor' => 5000, 'amount_paid_minor' => 1000, 'amount_remaining_minor' => 3000]);
    workspaceInvoice($subscription);
    $attention = collect(app(BillingWorkspaceQuery::class)->attention($subscription))->keyBy('currency');
    expect($attention['USD']['amount_minor'])->toBe(2825)->and($attention['EUR']['amount_minor'])->toBe(3000);
});

it('returns secure invoice details without provider metadata and blocks another tenant', function () {
    [$owner,$business,$subscription] = billingWorkspaceFixture();
    $invoice = workspaceInvoice($subscription);
    $this->actingAs($owner)->getJson(route('business.billing.invoices.show', [$business, $invoice->public_id]))->assertOk()
        ->assertJsonPath('total_minor', 10825)->assertJsonPath('line_items.0.amount_minor', 10000)
        ->assertJsonMissingPath('provider_invoice_id')->assertJsonMissingPath('business_id');
    [, $other, $otherSubscription] = billingWorkspaceFixture();
    $otherInvoice = workspaceInvoice($otherSubscription);
    $this->actingAs($owner)->getJson(route('business.billing.invoices.show', [$business, $otherInvoice->public_id]))->assertNotFound();
});

it('filters unsafe and non-provider document links', function () {
    [,,$subscription] = billingWorkspaceFixture();
    $invoice = workspaceInvoice($subscription, ['pdf_url' => 'javascript:alert(1)', 'hosted_url' => 'https://invoice.stripe.com.evil.test/invoice']);
    $data = app(BillingWorkspaceQuery::class)->invoice($invoice);
    expect($data['pdf_url'])->toBeNull()->and($data['hosted_url'])->toBeNull();
});

it('does not grant staff access to billing refresh or invoice evidence', function () {
    [$staff,$business] = createTenantMembership(StarterRole::BarberStylist);
    activateTestSubscription($business);
    $this->actingAs($staff)->postJson(route('business.billing.refresh', $business))->assertForbidden();
    $this->actingAs($staff)->getJson(route('business.billing.invoices.show', [$business, 'UNKNOWN']))->assertForbidden();
});

it('ignores provider events without ownership identifiers', function () {
    [,,$subscription] = billingWorkspaceFixture();
    $event = ['id' => 'evt_unbound', 'type' => 'invoice.payment_failed', 'created' => now()->timestamp, 'data' => ['object' => ['id' => 'in_unknown', 'object' => 'invoice']]];
    expect(app(StripeWebhookProcessor::class)->receiveVerified($event)->status)->toBe('ignored')
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)->and(BillingInvoice::count())->toBe(0);
});

it('rejects conflicting provider customer and tenant metadata', function (string $field) {
    [,$business,$subscription] = billingWorkspaceFixture();
    $object = workspaceProviderInvoice($subscription);
    if ($field === 'customer') {
        $object['customer'] = 'cus_another_tenant';
    } else {
        $object['metadata'] = ['business_public_id' => 'ANOTHER_BUSINESS'];
    }
    $handled = app(StripeWebhookProcessor::class)->synchronizeInvoiceSnapshot($object, 'invoice.payment_failed', now());
    expect($handled)->toBeFalse()->and(BillingInvoice::count())->toBe(0)->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
})->with(['customer', 'metadata']);

it('preserves paid evidence against older and same-second failed invoice events', function (int $delay) {
    [,,$subscription] = billingWorkspaceFixture();
    $processor = app(StripeWebhookProcessor::class);
    $paid = workspaceProviderInvoice($subscription, ['status' => 'paid', 'amount_paid' => 10825, 'amount_remaining' => 0]);
    $processor->synchronizeInvoiceSnapshot($paid, 'invoice.paid', now());
    $processor->synchronizeInvoiceSnapshot(workspaceProviderInvoice($subscription), 'invoice.payment_failed', now()->subSeconds($delay));
    expect(BillingInvoice::first()->status)->toBe('paid')->and(BillingInvoice::first()->amount_remaining_minor)->toBe(0)
        ->and(BillingPayment::first()->status)->toBe('succeeded')->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
})->with([0, 60]);

it('sums all provider discounts and taxes and preserves exact retry evidence', function () {
    [,,$subscription] = billingWorkspaceFixture();
    $object = workspaceProviderInvoice($subscription, ['total_discount_amounts' => [['amount' => 200], ['amount' => 300]], 'total_taxes' => [['amount' => 400], ['amount' => 425]], 'next_payment_attempt' => now()->addDay()->timestamp]);
    app(StripeWebhookProcessor::class)->synchronizeInvoiceSnapshot($object, 'invoice.payment_failed', now());
    $invoice = BillingInvoice::first();
    expect($invoice->discount_minor)->toBe(500)->and($invoice->tax_minor)->toBe(825)
        ->and($invoice->last_payment_failed_at->timestamp)->toBe(now()->timestamp)->and($invoice->next_payment_attempt_at->timestamp)->toBe(now()->addDay()->timestamp);
});

it('records a provider-confirmed future billing phase without granting its entitlements early', function () {
    [$owner,$business,$subscription] = billingWorkspaceFixture();
    $annual = BillingPlanPrice::create(['billing_plan_id' => $subscription->billing_plan_id, 'billing_interval' => 'annual', 'currency' => 'USD', 'amount_minor' => 100000, 'provider' => 'stripe', 'provider_price_id' => 'price_future_year', 'catalog_managed' => true, 'is_active' => true, 'effective_from' => now()->subDay()]);
    app(StripeWebhookProcessor::class)->synchronizeScheduleSnapshot(['subscription' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id, 'status' => 'active', 'phases' => [['start_date' => now()->addDays(20)->timestamp, 'items' => [['price' => $annual->provider_price_id]]]]], now());
    expect($subscription->fresh()->billing_plan_price_id)->not->toBe($annual->id)->and($subscription->fresh()->scheduled_billing_plan_price_id)->toBe($annual->id);
    $subscription->update(['billing_checked_at' => now()]);
    $this->actingAs($owner)->postJson(route('business.billing.plan-change.status', $business), ['price_id' => $annual->id])->assertOk()->assertJsonPath('status', 'scheduled');
});

it('accepts optional cancellation feedback and makes exact replay a no-op', function () {
    [$owner,$business,$subscription] = billingWorkspaceFixture();
    $provider = $this->mock(SubscriptionProvider::class);
    $provider->shouldReceive('cancelAtPeriodEnd')->once();
    $payload = ['version' => $subscription->version];
    $this->actingAs($owner)->postJson(route('business.billing.cancel', $business), $payload)->assertOk();
    $this->actingAs($owner)->postJson(route('business.billing.cancel', $business), $payload)->assertOk();
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::CancelScheduled)
        ->and(DB::table('billing_notices')->where('type', 'cancellation_scheduled')->count())->toBe(1);
});

it('rejects stale cancellation and reactivation after paid access ends', function () {
    [$owner,$business,$subscription] = billingWorkspaceFixture();
    $provider = $this->mock(SubscriptionProvider::class);
    $provider->shouldNotReceive('cancelAtPeriodEnd');
    $provider->shouldNotReceive('reactivate');
    $subscription->update(['version' => 2]);
    $this->actingAs($owner)->postJson(route('business.billing.cancel', $business), ['version' => 1])->assertConflict();
    $subscription->update(['status' => 'cancel_scheduled', 'cancel_at' => now()->subSecond()]);
    $this->actingAs($owner)->postJson(route('business.billing.reactivate', $business), [])->assertConflict();
});

it('reactivates a scheduled cancellation once and preserves replay safety', function () {
    [$owner,$business,$subscription] = billingWorkspaceFixture();
    $subscription->update(['status' => 'cancel_scheduled', 'cancel_at' => now()->addDays(20)]);
    $provider = $this->mock(SubscriptionProvider::class);
    $provider->shouldReceive('reactivate')->once();
    $this->actingAs($owner)->postJson(route('business.billing.reactivate', $business), [])->assertOk();
    $this->actingAs($owner)->postJson(route('business.billing.reactivate', $business), [])->assertOk();
    expect(DB::table('billing_notices')->where('type', 'subscription_reactivated')->count())->toBe(1);
});

it('throttles freshly verified account refresh without another provider request', function () {
    [$owner,$business,$subscription] = billingWorkspaceFixture();
    $subscription->update(['account_checked_at' => now()]);
    $this->mock(StripeSubscriptionReconciler::class)->shouldNotReceive('reconcileAccount');
    $this->actingAs($owner)->postJson(route('business.billing.refresh', $business))->assertOk()->assertJsonPath('status', 'recently_checked');
});

it('does not send a new failure notice for an older subscription snapshot', function () {
    [,,$subscription] = billingWorkspaceFixture();
    $subscription->update(['provider_state_at' => now()]);
    app(SubscriptionLifecycleManager::class)->renewalFailed($subscription, 2, now()->subMinute());
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)->and(DB::table('billing_notices')->count())->toBe(0);
});
