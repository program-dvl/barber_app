<?php

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Models\PaymentIntent;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Services\CashCloseService;
use App\Domain\MoneyCommerce\Services\CheckoutService;
use App\Domain\MoneyCommerce\Services\DepositPolicyService;
use App\Domain\MoneyCommerce\Services\DepositService;
use App\Domain\MoneyCommerce\Services\PaymentWebhookProcessor;
use App\Domain\MoneyCommerce\Services\ReceiptService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array{business:Business,location:Location,appointment:Appointment} */
function commercePath(): array
{
    $business = Business::factory()->create(['currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata']);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'Asia/Kolkata']);
    $service = Service::query()->create(['business_id' => $business->id, 'kind' => 'service', 'name' => 'Cut', 'price_type' => 'fixed', 'price_minor' => 3000, 'currency_code' => 'INR', 'duration_minutes' => 30, 'minimum_notice_minutes' => 0, 'maximum_advance_days' => 30, 'client_eligibility' => 'all', 'is_active' => true]);
    $appointment = Appointment::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'idempotency_key' => 'appointment-'.$business->id, 'request_hash' => hash('sha256', 'appointment'), 'status' => 'completed', 'source' => 'reception', 'starts_at_utc' => now(), 'ends_at_utc' => now()->addMinutes(30), 'time_zone' => 'Asia/Kolkata', 'local_starts_at' => now()->setTimezone('Asia/Kolkata')->format(DATE_ATOM), 'local_ends_at' => now()->addMinutes(30)->setTimezone('Asia/Kolkata')->format(DATE_ATOM), 'price_minor' => 3000, 'currency_code' => 'INR', 'completed_at' => now()]);
    AppointmentServiceLine::query()->create(['business_id' => $business->id, 'appointment_id' => $appointment->id, 'service_id' => $service->id, 'sequence' => 1, 'name' => 'Cut', 'price_minor' => 3000, 'currency_code' => 'INR', 'bookable_minutes' => 30, 'configuration_snapshot' => ['taxRateBps' => 1800]]);
    CommerceSetting::query()->create(['business_id' => $business->id, 'currency_code' => 'INR', 'tax_inclusive' => true, 'default_tax_rate_bps' => 1800, 'discount_manager_limit_bps' => 1000]);

    return compact('business', 'location', 'appointment');
}

it('reconciles a deposit and split tender, preserves a partial refund, and reproduces a receipt', function () {
    $path = commercePath();
    $checkout = app(CheckoutService::class);
    $sale = $checkout->openForAppointment($path['appointment'], [], [['staff_profile_id' => null, 'amount_minor' => 500]]);
    expect($sale->total_minor)->toBe(3500)->and($sale->tax_minor)->toBe(458);

    $depositPayment = PaymentTransaction::query()->create(['business_id' => $path['business']->id, 'appointment_id' => $path['appointment']->id, 'kind' => 'payment', 'method' => 'card', 'provider' => 'stripe', 'provider_reference' => 'pi-deposit-'.$path['business']->id, 'idempotency_key' => 'deposit-payment', 'amount_minor' => 1000, 'currency_code' => 'INR', 'occurred_at' => now()]);
    $deposit = app(DepositService::class)->bind($path['appointment'], $depositPayment, ['amount_minor' => 1000, 'currency_code' => 'INR']);
    $sale = $checkout->applyDeposit($sale, $deposit, 1000, 'deposit-apply');
    $cash = $checkout->recordTender($sale, 'cash', 1500, 'cash-tender');
    $card = $checkout->recordTender($sale->fresh(), 'card', 1000, 'card-tender', [], 'stripe', 'pi-balance-'.$path['business']->id);
    $replay = $checkout->recordTender($sale->fresh(), 'card', 1000, 'card-tender');
    expect($replay->id)->toBe($card->id)->and($sale->fresh()->status)->toBe('completed')->and($deposit->fresh()->applied_minor)->toBe(1000);
    $refund = $checkout->refund($sale->fresh(), $cash, 500, 'cash-refund', 'Client was overcharged.');
    $receipt = app(ReceiptService::class)->issue($sale->fresh());
    expect($refund->amount_minor)->toBe(500)->and($sale->fresh()->refunded_minor)->toBe(500)->and($receipt->content_hash)->toHaveLength(64)->and(app(ReceiptService::class)->issue($sale->fresh())->id)->toBe($receipt->id);
    expect(PaymentTransaction::query()->where('sale_id', $sale->id)->where('kind', 'payment')->sum('amount_minor') + $deposit->original_amount_minor - PaymentTransaction::query()->where('sale_id', $sale->id)->where('kind', 'refund')->sum('amount_minor'))->toBe(3000);
});

it('denies an excessive unapproved discount and records a reasoned cash variance', function () {
    $path = commercePath();
    expect(fn () => app(CheckoutService::class)->openForAppointment($path['appointment'], [['description' => 'Retail pomade', 'quantity' => 1, 'unit_price_minor' => 1000, 'discount_minor' => 500]], []))
        ->toThrow(DomainException::class, 'Discount exceeds');
    $close = app(CashCloseService::class)->close($path['location'], CarbonImmutable::now('Asia/Kolkata'), 500, 450, 'Till recount pending', 1);
    expect($close->expected_cash_minor)->toBe(500)->and($close->variance_minor)->toBe(-50)->and($close->outstanding_balance_minor)->toBe(0);
});

it('deduplicates and orders verified provider evidence without losing a successful charge', function () {
    $path = commercePath();
    $intent = PaymentIntent::query()->create(['business_id' => $path['business']->id, 'purpose' => 'deposit', 'provider' => 'stripe', 'provider_intent_id' => 'pi-webhook-'.$path['business']->id, 'idempotency_key' => 'webhook-intent', 'request_hash' => hash('sha256', 'webhook'), 'amount_minor' => 1000, 'currency_code' => 'INR', 'source_snapshot' => ['amount_minor' => 1000, 'currency_code' => 'INR']]);
    $processor = app(PaymentWebhookProcessor::class);
    $success = ['event_id' => 'evt-success-'.$path['business']->id, 'event_type' => 'payment_intent.succeeded', 'created_at' => CarbonImmutable::parse('2026-08-15 10:00:00 UTC'), 'payload' => ['data' => ['object' => ['id' => $intent->provider_intent_id, 'latest_charge' => 'ch_1']]]];
    $failedLater = ['event_id' => 'evt-failed-'.$path['business']->id, 'event_type' => 'payment_intent.payment_failed', 'created_at' => CarbonImmutable::parse('2026-08-15 10:01:00 UTC'), 'payload' => ['data' => ['object' => ['id' => $intent->provider_intent_id]]]];
    $processor->ingest('stripe', $success);
    $processor->ingest('stripe', $success);
    $processor->ingest('stripe', $failedLater);
    expect($intent->fresh()->status)->toBe('succeeded')->and(PaymentTransaction::query()->where('payment_intent_id', $intent->id)->count())->toBe(1)->and(DB::table('payment_reconciliation_tasks')->where('payment_intent_id', $intent->id)->where('kind', 'missing_booking_flow')->exists())->toBeTrue();
});

it('resolves no, fixed, percentage, full, new-client, threshold, and prior-no-show deposit policies', function () {
    $path = commercePath();
    $settings = CommerceSetting::query()->where('business_id', $path['business']->id)->firstOrFail();
    $policy = app(DepositPolicyService::class);
    expect($policy->resolve($path['business'], [['priceMinor' => 3000, 'depositType' => 'none']], 'existing')['amount_minor'])->toBe(0);
    expect($policy->resolve($path['business'], [['priceMinor' => 3000, 'depositType' => 'fixed', 'depositValue' => 900]], 'existing')['amount_minor'])->toBe(900);
    expect($policy->resolve($path['business'], [['priceMinor' => 3000, 'depositType' => 'percentage', 'depositValue' => 1000]], 'existing')['amount_minor'])->toBe(300);
    expect($policy->resolve($path['business'], [['priceMinor' => 3000, 'depositType' => 'full']], 'existing')['amount_minor'])->toBe(3000);
    $settings->update(['default_deposit_type' => 'fixed', 'default_deposit_value' => 800, 'deposit_new_clients_only' => true]);
    expect($policy->resolve($path['business'], [['priceMinor' => 3000, 'depositType' => 'none']], 'existing')['amount_minor'])->toBe(0)
        ->and($policy->resolve($path['business'], [['priceMinor' => 3000, 'depositType' => 'none']], 'new')['amount_minor'])->toBe(800);
    $settings->update(['deposit_new_clients_only' => false, 'deposit_threshold_minor' => 3500, 'deposit_prior_no_show_count' => 2]);
    $client = Client::factory()->create(['business_id' => $path['business']->id]);
    foreach ([1, 2] as $number) {
        Appointment::query()->create(['business_id' => $path['business']->id, 'location_id' => $path['location']->id, 'client_id' => $client->id, 'idempotency_key' => 'no-show-'.$number.'-'.$path['business']->id, 'request_hash' => hash('sha256', 'no-show-'.$number), 'status' => 'no_show', 'source' => 'reception', 'starts_at_utc' => now()->subDays($number), 'ends_at_utc' => now()->subDays($number)->addMinutes(30), 'time_zone' => 'Asia/Kolkata', 'local_starts_at' => now()->subDays($number)->setTimezone('Asia/Kolkata')->format(DATE_ATOM), 'local_ends_at' => now()->subDays($number)->addMinutes(30)->setTimezone('Asia/Kolkata')->format(DATE_ATOM), 'price_minor' => 0, 'currency_code' => 'INR']);
    }
    expect($policy->resolve($path['business'], [['priceMinor' => 3000, 'depositType' => 'none']], 'existing')['amount_minor'])->toBe(0)
        ->and($policy->resolve($path['business'], [['priceMinor' => 4000, 'depositType' => 'none']], 'existing')['amount_minor'])->toBe(0)
        ->and($policy->resolve($path['business'], [['priceMinor' => 4000, 'depositType' => 'none']], 'existing', $client->id)['amount_minor'])->toBe(800);
});

it('keeps payment access tenant-scoped and preserves an explicit manager waiver', function () {
    $first = commercePath();
    $second = commercePath();
    $sale = app(CheckoutService::class)->openForAppointment($second['appointment']);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $first['business']->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $user, 'Commerce tenant test.');
    $this->actingAs($user)->post(route('business.checkout.tender', [$second['business'], $sale]), ['method' => 'cash', 'amount_minor' => 100, 'idempotency_key' => 'cross-tenant'])->assertForbidden();

    $payment = PaymentTransaction::query()->create(['business_id' => $first['business']->id, 'appointment_id' => $first['appointment']->id, 'kind' => 'payment', 'method' => 'card', 'idempotency_key' => 'waiver-payment', 'amount_minor' => 900, 'currency_code' => 'INR', 'occurred_at' => now()]);
    $deposit = app(DepositService::class)->bind($first['appointment'], $payment, ['amount_minor' => 900, 'currency_code' => 'INR', 'deposit_refundable_before_cutoff' => false, 'cancellation_fee_minor' => 900]);
    expect(app(DepositService::class)->settleCancellation($deposit, false, false, true, 'waiver', 'Manager waiver.'))->toBeNull()
        ->and($deposit->fresh()->remainingMinor())->toBe(900);
});
