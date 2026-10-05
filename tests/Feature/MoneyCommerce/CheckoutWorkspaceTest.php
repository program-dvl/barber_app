<?php

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\InventoryLedger;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Models\Deposit;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleReceipt;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
afterEach(fn () => app(TenantContext::class)->clear());

function checkoutFixture(StarterRole $role = StarterRole::Owner): array
{
    [$user, $business, $membership] = createTenantMembership($role);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'Asia/Kolkata']);
    $membership->locations()->attach($location->id, ['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'status' => 'active']);
    $staff->locations()->attach($location->id, ['business_id' => $business->id]);
    $service = Service::query()->create(['business_id' => $business->id, 'name' => 'Signature cut', 'kind' => 'service', 'price_minor' => 5000, 'currency_code' => 'INR', 'duration_minutes' => 40, 'is_active' => true]);
    $service->locations()->attach($location->id, ['business_id' => $business->id, 'is_eligible' => true, 'price_minor' => 6000]);
    StaffServiceAssignment::query()->create(['business_id' => $business->id, 'service_id' => $service->id, 'staff_profile_id' => $staff->id, 'is_active' => true, 'is_qualified' => true, 'price_minor' => 7000]);
    $client = Client::factory()->create(['business_id' => $business->id, 'name' => 'Olivia Review']);
    $appointment = Appointment::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'client_id' => $client->id, 'client_name' => $client->name, 'status' => 'completed', 'source' => 'walk_in', 'idempotency_key' => 'checkout-review', 'request_hash' => hash('sha256', 'checkout'), 'starts_at_utc' => now()->subHour(), 'ends_at_utc' => now(), 'time_zone' => 'Asia/Kolkata', 'local_starts_at' => now()->subHour()->toIso8601String(), 'local_ends_at' => now()->toIso8601String(), 'price_minor' => 5000, 'currency_code' => 'INR']);
    $line = AppointmentServiceLine::query()->create(['business_id' => $business->id, 'appointment_id' => $appointment->id, 'service_id' => $service->id, 'primary_staff_profile_id' => $staff->id, 'sequence' => 1, 'name' => 'Booked signature cut', 'price_minor' => 5000, 'currency_code' => 'INR', 'bookable_minutes' => 40, 'configuration_snapshot' => ['taxRateBps' => 1000]]);
    CommerceSetting::query()->create(['business_id' => $business->id, 'currency_code' => 'INR', 'tax_inclusive' => false, 'default_tax_rate_bps' => 1000, 'discount_manager_limit_bps' => 2000]);
    $product = InventoryProduct::query()->create(['business_id' => $business->id, 'name' => 'Matte pomade', 'sku' => 'MP-01', 'barcode' => '123456', 'sale_price_minor' => 2000, 'tax_rate_bps' => 1000, 'currency_code' => 'INR', 'status' => 'active', 'current_stock' => 0]);
    app(InventoryLedger::class)->importOpeningStock($product, $location, 4, 'review-stock');
    $basket = ['items' => [['booked_line_id' => $line->id, 'staff_profile_id' => $staff->id, 'quantity' => 1]], 'tips' => [], 'apply_deposit' => true];

    return compact('user', 'business', 'membership', 'location', 'staff', 'service', 'client', 'appointment', 'line', 'product', 'basket');
}
function checkoutPrepare($test, array $t, ?array $basket = null): Sale
{
    $basket ??= $t['basket'];
    $quote = $test->actingAs($t['user'])->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $basket)->assertOk()->json('quote');
    $test->postJson(route('business.checkout.prepare', [$t['business'], $t['appointment']]), [...$basket, 'quote_key' => $quote['quote_key']])->assertOk();

    return Sale::query()->where('appointment_id', $t['appointment']->id)->firstOrFail();
}
function checkoutCommand(int $amount, string $key = 'command-1'): array
{
    return ['idempotency_key' => $key, 'received_confirmed' => true, 'payments' => [['method' => 'cash', 'amount_minor' => $amount]]];
}

it('preloads source prices and qualified variants, searches barcode and keeps foreign stock private', function () {
    $t = checkoutFixture();
    $this->actingAs($t['user'])->getJson(route('business.checkout.visit', [$t['business'], $t['appointment']]))->assertOk()->assertJsonPath('items.0.unit_price_minor', 5000)->assertJsonPath('appointment.source', 'walk_in')->assertJsonMissingPath('appointment.client_mobile');
    $this->getJson(route('business.checkout.catalogue', ['business' => $t['business'], 'appointment' => $t['appointment'], 'kind' => 'service']))->assertOk()->assertJsonPath('items.0.variants.0.price_minor', 7000);
    $this->getJson(route('business.checkout.catalogue', ['business' => $t['business'], 'appointment' => $t['appointment'], 'kind' => 'product', 'search' => '123456']))->assertOk()->assertJsonPath('items.0.stock', 4);
    $retail = $t['basket'];
    $retail['items'][] = ['product_public_id' => $t['product']->public_id, 'quantity' => 1];
    $this->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $retail)->assertOk()->assertJsonMissingPath('quote.lines.1.source_snapshot')->assertJsonMissingPath('quote.lines.1.cost_minor');
    $basket = $t['basket'];
    $basket['items'][] = ['service_public_id' => $t['service']->public_id, 'staff_profile_id' => $t['staff']->id, 'quantity' => 1];
    $sale = checkoutPrepare($this, $t, $basket);
    expect($sale->total_minor)->toBe(13200)->and($sale->lines()->count())->toBe(2);
});

it('revalidates the quote and refuses price drift without creating a sale', function () {
    $t = checkoutFixture();
    $quote = $this->actingAs($t['user'])->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $t['basket'])->assertOk()->json('quote');
    $t['line']->update(['price_minor' => 6000]);
    $this->postJson(route('business.checkout.prepare', [$t['business'], $t['appointment']]), [...$t['basket'], 'quote_key' => $quote['quote_key']])->assertUnprocessable()->assertJsonValidationErrors('checkout');
    expect(Sale::query()->count())->toBe(0);
});

it('enforces discounts, overrides and staff lineage on the server rather than trusting approval flags', function () {
    $t = checkoutFixture(StarterRole::Receptionist);
    $basket = $t['basket'];
    $basket['items'][0]['discount_minor'] = 500;
    $this->actingAs($t['user'])->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), [...$basket, 'discount_approved' => true])->assertUnprocessable();
    $basket = $t['basket'];
    $basket['items'][0]['unit_price_minor'] = 1;
    $basket['reason'] = 'Override';
    $this->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $basket)->assertUnprocessable();
    $this->postJson(route('business.checkout.open', [$t['business'], $t['appointment']]), ['lines' => [['description' => 'Injected', 'quantity' => 1, 'unit_price_minor' => 0]], 'discount_approved' => true])->assertUnprocessable();
    $other = checkoutFixture();
    $basket = $t['basket'];
    $basket['items'][0]['staff_profile_id'] = $other['staff']->id;
    $this->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $basket)->assertUnprocessable();
    expect(Sale::query()->count())->toBe(0);
});

it('requires adjustment reasons, preserves overridden sources and allocates multi-staff tips', function () {
    $t = checkoutFixture();
    $basket = $t['basket'];
    $basket['items'][0]['unit_price_minor'] = 4500;
    $basket['items'][0]['discount_minor'] = 1000;
    $this->actingAs($t['user'])->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $basket)->assertUnprocessable();
    $second = StaffProfile::factory()->create(['business_id' => $t['business']->id, 'status' => 'active']);
    $second->locations()->attach($t['location']->id, ['business_id' => $t['business']->id]);
    $basket['reason'] = 'Manager approved correction';
    $basket['tips'] = [['staff_profile_id' => $t['staff']->id, 'amount_minor' => 500], ['staff_profile_id' => $second->id, 'amount_minor' => 300]];
    $duplicate = $basket;
    $duplicate['tips'][] = $basket['tips'][0];
    $this->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $duplicate)->assertUnprocessable();
    $sale = checkoutPrepare($this, $t, $basket);
    expect($sale->total_minor)->toBe(4650)->and(DB::table('sale_tip_allocations')->where('sale_id', $sale->id)->count())->toBe(2)->and($sale->lines->first()->source_snapshot['base_price_minor'])->toBe(5000)->and(AuditEvent::query()->where('action', 'sale.prepared')->first()->reason)->toBe($basket['reason']);
});

it('applies verified appointment deposits once and completes a fully prepaid sale without a fake tender', function () {
    $t = checkoutFixture();
    $payment = PaymentTransaction::query()->create(['business_id' => $t['business']->id, 'appointment_id' => $t['appointment']->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'card', 'idempotency_key' => 'deposit', 'amount_minor' => 6000, 'currency_code' => 'INR', 'occurred_at' => now()]);
    $deposit = Deposit::query()->create(['business_id' => $t['business']->id, 'appointment_id' => $t['appointment']->id, 'client_id' => $t['client']->id, 'payment_transaction_id' => $payment->id, 'original_amount_minor' => 6000, 'currency_code' => 'INR', 'policy_snapshot' => []]);
    $sale = checkoutPrepare($this, $t);
    expect($sale->status)->toBe('completed')->and($sale->deposit_applied_minor)->toBe(5500)->and($deposit->fresh()->remainingMinor())->toBe(500)->and($sale->transactions()->count())->toBe(0)->and(SaleReceipt::query()->count())->toBe(1);
    $quoteKey = data_get($sale->calculation_snapshot, 'workspace_quote_key');
    $this->postJson(route('business.checkout.prepare', [$t['business'], $t['appointment']]), [...$t['basket'], 'quote_key' => $quoteKey])->assertOk();
    expect($deposit->fresh()->applied_minor)->toBe(5500)->and(DB::table('deposit_allocations')->count())->toBe(1);
});

it('keeps partial payments open, replays exact split commands and rejects key changes and pay-later tender', function () {
    $t = checkoutFixture();
    $sale = checkoutPrepare($this, $t);
    $route = route('business.checkout.payments', [$t['business'], $sale]);
    $this->postJson($route, checkoutCommand(1000))->assertOk()->assertJsonPath('sale.status', 'open')->assertJsonPath('sale.balance_minor', 4500);
    $this->postJson($route, checkoutCommand(1000))->assertOk();
    $this->postJson($route, checkoutCommand(1200))->assertUnprocessable();
    $this->postJson($route, ['idempotency_key' => 'split', 'received_confirmed' => true, 'payments' => [['method' => 'cash', 'amount_minor' => 2000], ['method' => 'card', 'amount_minor' => 2500]]])->assertOk()->assertJsonPath('sale.status', 'completed');
    expect($sale->transactions()->count())->toBe(3)->and(SaleReceipt::query()->count())->toBe(1)->and(AuditEvent::query()->where('action', 'sale.payment.recorded')->count())->toBe(3);
    $this->postJson(route('business.checkout.tender', [$t['business'], $sale]), ['method' => 'pay_later', 'amount_minor' => 1, 'idempotency_key' => 'later'])->assertUnprocessable();
});

it('rolls back the entire split if final stock fails and permits safe replay only after repair', function () {
    $t = checkoutFixture();
    $basket = $t['basket'];
    $basket['items'][] = ['product_public_id' => $t['product']->public_id, 'quantity' => 4];
    $sale = checkoutPrepare($this, $t, $basket);
    app(InventoryLedger::class)->adjust($t['product'], $t['location'], -1, $t['membership'], 'Other sale consumed stock', 'reduce');
    $command = ['idempotency_key' => 'stock-split', 'received_confirmed' => true, 'payments' => [['method' => 'cash', 'amount_minor' => 5000], ['method' => 'card', 'amount_minor' => 9300]]];
    $this->postJson(route('business.checkout.payments', [$t['business'], $sale]), $command)->assertUnprocessable();
    expect($sale->fresh()->paid_minor)->toBe(0)->and($sale->transactions()->count())->toBe(0)->and(SaleReceipt::query()->count())->toBe(0);
    app(InventoryLedger::class)->adjust($t['product'], $t['location'], 1, $t['membership'], 'Verified stock correction', 'restore');
    $this->postJson(route('business.checkout.payments', [$t['business'], $sale]), $command)->assertOk()->assertJsonPath('sale.status', 'completed');
    $this->postJson(route('business.checkout.payments', [$t['business'], $sale]), $command)->assertOk();
    expect($sale->fresh()->paid_minor)->toBe(14300)->and($sale->transactions()->count())->toBe(2)->and($t['product']->fresh()->current_stock)->toBe(0);
});

it('scopes history and receipt capabilities separately and denies foreign or inaccessible visits', function () {
    $t = checkoutFixture(StarterRole::Receptionist);
    $sale = checkoutPrepare($this, $t);
    $this->get(route('business.checkout.index', $t['business']))->assertOk()->assertInertia(fn (Assert $p) => $p->where('permissions.history', false)->has('sales', 0));
    $this->get(route('business.checkout.receipt', [$t['business'], $sale]))->assertUnprocessable();
    $this->postJson(route('business.checkout.payments', [$t['business'], $sale]), checkoutCommand(5500))->assertOk();
    $this->get(route('business.checkout.receipt', [$t['business'], $sale]))->assertOk();
    $other = checkoutFixture();
    $this->getJson(route('business.checkout.visit', [$t['business'], $other['appointment']]))->assertNotFound();
    $location = Location::factory()->create(['business_id' => $t['business']->id]);
    $t['appointment']->update(['location_id' => $location->id]);
    $this->getJson(route('business.checkout.visit', [$t['business'], $t['appointment']]))->assertForbidden();
});

it('paginates and filters history by reference, staff, status and local calendar date', function () {
    $t = checkoutFixture();
    $sale = checkoutPrepare($this, $t);
    $this->get(route('business.checkout.index', ['business' => $t['business'], 'search' => $t['appointment']->booking_reference, 'staff' => $t['staff']->id, 'status' => 'open', 'from' => now()->setTimezone('Asia/Kolkata')->format('Y-m-d'), 'to' => now()->setTimezone('Asia/Kolkata')->format('Y-m-d')]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('sales', 1)->where('salesPagination.total', 1));
    $this->get(route('business.checkout.index', ['business' => $t['business'], 'to' => now()->setTimezone('Asia/Kolkata')->format('Y-m-d')]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('sales', 1));
    $this->get(route('business.checkout.index', ['business' => $t['business'], 'search' => 'not-found']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('sales', 0));
});

it('records append-only manual refunds with cumulative stock bounds and blocks provider refund fabrication', function () {
    $t = checkoutFixture();
    $basket = $t['basket'];
    $basket['items'][] = ['product_public_id' => $t['product']->public_id, 'quantity' => 1];
    $sale = checkoutPrepare($this, $t, $basket);
    $this->postJson(route('business.checkout.payments', [$t['business'], $sale]), checkoutCommand(7700))->assertOk();
    $receipt = SaleReceipt::query()->firstOrFail();
    $hash = $receipt->content_hash;
    $snapshot = $receipt->snapshot;
    $payment = $sale->transactions()->first();
    $line = $sale->lines()->where('kind', 'product')->first();
    $refund = ['idempotency_key' => 'return', 'amount_minor' => 2000, 'reason' => 'Unopened return', 'returned_confirmed' => true, 'line_refunds' => [['sale_line_id' => $line->id, 'amount_minor' => 2000, 'quantity' => 1, 'disposition' => 'restock']]];
    $route = route('business.checkout.refund', [$t['business'], $sale, $payment]);
    $this->postJson($route, [...$refund, 'line_refunds' => [$refund['line_refunds'][0], $refund['line_refunds'][0]], 'amount_minor' => 4000])->assertUnprocessable();
    $this->postJson($route, $refund)->assertOk();
    $this->postJson($route, $refund)->assertOk();
    $changed = $refund;
    $changed['line_refunds'][0]['quantity'] = 0;
    $this->postJson($route, $changed)->assertUnprocessable();
    $this->postJson($route, [...$refund, 'idempotency_key' => 'return-again', 'amount_minor' => 100])->assertUnprocessable();
    expect($t['product']->fresh()->current_stock)->toBe(4)->and($receipt->fresh()->content_hash)->toBe($hash)->and($receipt->fresh()->snapshot)->toBe($snapshot)->and($sale->transactions()->where('kind', 'refund')->count())->toBe(1);
    $provider = PaymentTransaction::query()->create(['business_id' => $t['business']->id, 'sale_id' => $sale->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'card', 'provider' => 'stripe', 'idempotency_key' => 'provider', 'amount_minor' => 100, 'currency_code' => 'INR', 'occurred_at' => now()]);
    $this->postJson(route('business.checkout.refund', [$t['business'], $sale, $provider]), ['idempotency_key' => 'provider-refund', 'amount_minor' => 100, 'reason' => 'Requested', 'returned_confirmed' => true])->assertUnprocessable();
});

it('allows accountants to inspect history without preparing or taking payment', function () {
    $t = checkoutFixture(StarterRole::Accountant);
    $this->actingAs($t['user'])->get(route('business.checkout.index', $t['business']))->assertOk()->assertInertia(fn (Assert $p) => $p->where('permissions.history', true)->where('permissions.checkout', false)->has('appointments', 0));
    $this->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $t['basket'])->assertForbidden();
});

it('applies a later verified deposit to a saved sale and safely replays completion', function () {
    $t = checkoutFixture();
    $sale = checkoutPrepare($this, $t);
    $payment = PaymentTransaction::query()->create(['business_id' => $t['business']->id, 'appointment_id' => $t['appointment']->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'card', 'idempotency_key' => 'later-deposit', 'amount_minor' => 6000, 'currency_code' => 'INR', 'occurred_at' => now()]);
    $deposit = Deposit::query()->create(['business_id' => $t['business']->id, 'appointment_id' => $t['appointment']->id, 'client_id' => $t['client']->id, 'payment_transaction_id' => $payment->id, 'original_amount_minor' => 6000, 'currency_code' => 'INR', 'policy_snapshot' => []]);
    $route = route('business.checkout.deposit', [$t['business'], $sale]);
    $this->postJson($route, ['confirmed' => true])->assertOk()->assertJsonPath('sale.status', 'completed')->assertJsonPath('sale.balance_minor', 0);
    $this->postJson($route, ['confirmed' => true])->assertOk();
    expect($deposit->fresh()->applied_minor)->toBe(5500)->and(DB::table('deposit_allocations')->count())->toBe(1)->and(AuditEvent::query()->where('action', 'sale.deposit.applied')->count())->toBe(1);
});

it('completes a fully discounted zero-value sale without fabricating received cash', function () {
    $t = checkoutFixture();
    $basket = $t['basket'];
    $basket['items'][0]['discount_minor'] = 5000;
    $basket['reason'] = 'Owner approved complimentary service';
    $sale = checkoutPrepare($this, $t, $basket);
    expect($sale->status)->toBe('completed')->and($sale->total_minor)->toBe(0)->and($sale->paid_minor)->toBe(0)->and($sale->transactions()->count())->toBe(0)->and(SaleReceipt::query()->count())->toBe(1);
});

it('does not apply deposit evidence belonging to another tenant or appointment', function () {
    $t = checkoutFixture();
    $other = checkoutFixture();
    $payment = PaymentTransaction::query()->create(['business_id' => $other['business']->id, 'appointment_id' => $other['appointment']->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'card', 'idempotency_key' => 'foreign-evidence', 'amount_minor' => 6000, 'currency_code' => 'INR', 'occurred_at' => now()]);
    Deposit::query()->create(['business_id' => $t['business']->id, 'appointment_id' => $t['appointment']->id, 'client_id' => $t['client']->id, 'payment_transaction_id' => $payment->id, 'original_amount_minor' => 6000, 'currency_code' => 'INR', 'policy_snapshot' => []]);
    $sale = checkoutPrepare($this, $t);
    expect($sale->deposit_applied_minor)->toBe(0)->and($sale->balance_minor)->toBe(5500);
    $this->getJson(route('business.checkout.show', [$t['business'], $sale]))->assertOk()->assertJsonPath('sale.deposit_available_minor', 0);
});
