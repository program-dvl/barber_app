<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\Commissions\Services\CommissionLedger;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\InventoryLedger;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Models\Deposit;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleLine;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\Reporting\Services\InstrumentationService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Money\MoneyCalculator;
use DomainException;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly MoneyCalculator $money,
        private readonly DepositService $deposits,
        private readonly InventoryLedger $inventory,
        private readonly CommissionLedger $commissions,
        private readonly InstrumentationService $instrumentation,
    ) {}

    /** @param list<array<string,mixed>> $extraLines @param list<array{staff_profile_id:?int,amount_minor:int}> $tips */
    public function openForAppointment(Appointment $appointment, array $extraLines = [], array $tips = [], bool $discountApproved = false): Sale
    {
        return DB::transaction(function () use ($appointment, $extraLines, $tips, $discountApproved): Sale {
            $existing = Sale::query()->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing->load('lines');
            }
            $settings = CommerceSetting::query()->firstOrCreate(['business_id' => $appointment->business_id], ['currency_code' => $appointment->currency_code]);
            $appointment->loadMissing('serviceLines');
            $lines = $appointment->serviceLines->map(fn ($line) => ['kind' => 'service', 'source_type' => get_class($line), 'source_id' => $line->id, 'service_id' => $line->service_id, 'staff_profile_id' => $line->primary_staff_profile_id, 'description' => $line->name, 'quantity' => 1, 'unit_price_minor' => $line->price_minor, 'tax_rate_bps' => (int) data_get($line->configuration_snapshot, 'taxRateBps', $settings->default_tax_rate_bps), 'discount_minor' => 0, 'source_snapshot' => [...$line->configuration_snapshot, 'service_id' => $line->service_id]])->all();
            foreach ($extraLines as $extra) {
                if (($extra['kind'] ?? 'product') === 'product' && ($extra['source_id'] ?? null)) {
                    $product = InventoryProduct::query()->forBusiness($appointment->business_id)->findOrFail($extra['source_id']);
                    $extra = [...$extra, 'source_type' => InventoryProduct::class, 'description' => $product->name, 'unit_price_minor' => $extra['unit_price_minor'] ?? $product->sale_price_minor, 'tax_rate_bps' => $extra['tax_rate_bps'] ?? $product->tax_rate_bps, 'source_snapshot' => ['product_id' => $product->id, 'sku' => $product->sku, 'barcode' => $product->barcode, 'cost_minor' => $product->cost_minor, 'catalog_price_minor' => $product->sale_price_minor]];
                }
                $lines[] = [...$extra, 'kind' => $extra['kind'] ?? 'product', 'source_type' => $extra['source_type'] ?? null, 'source_id' => $extra['source_id'] ?? null, 'staff_profile_id' => $extra['staff_profile_id'] ?? null, 'source_snapshot' => $extra['source_snapshot'] ?? ['entered_at' => now()->utc()->toIso8601String()]];
            }
            $tipMinor = array_sum(array_column($tips, 'amount_minor'));
            $discount = array_sum(array_map(fn (array $line) => (int) ($line['discount_minor'] ?? 0), $lines));
            $raw = array_sum(array_map(fn (array $line) => (int) $line['quantity'] * (int) $line['unit_price_minor'], $lines));
            if ($raw > 0 && ! $discountApproved && $discount * 10000 > $raw * $settings->discount_manager_limit_bps) {
                throw new DomainException('Discount exceeds the approval threshold.');
            }
            $calculation = $this->money->calculate($lines, $appointment->currency_code, $settings->tax_inclusive, $tipMinor);
            $sale = Sale::query()->create(['business_id' => $appointment->business_id, 'location_id' => $appointment->location_id, 'appointment_id' => $appointment->id, 'client_id' => $appointment->client_id, 'currency_code' => $appointment->currency_code, 'subtotal_minor' => $calculation['subtotal_minor'], 'discount_minor' => $calculation['discount_minor'], 'tax_minor' => $calculation['tax_minor'], 'tip_minor' => $tipMinor, 'total_minor' => $calculation['total_minor'], 'balance_minor' => $calculation['total_minor'], 'calculation_snapshot' => $calculation]);
            foreach ($calculation['lines'] as $sequence => $line) {
                SaleLine::query()->create(['business_id' => $sale->business_id, 'sale_id' => $sale->id, 'sequence' => $sequence + 1, ...collect($line)->only(['kind', 'source_type', 'source_id', 'service_id', 'staff_profile_id', 'description', 'quantity', 'unit_price_minor', 'tax_rate_bps', 'discount_minor', 'source_snapshot'])->all()]);
            }
            foreach ($tips as $tip) {
                DB::table('sale_tip_allocations')->insert(['business_id' => $sale->business_id, 'sale_id' => $sale->id, 'staff_profile_id' => $tip['staff_profile_id'], 'amount_minor' => $tip['amount_minor'], 'created_at' => now(), 'updated_at' => now()]);
            }

            return $sale->load('lines');
        });
    }

    public function applyDeposit(Sale $sale, Deposit $deposit, int $amountMinor, string $idempotencyKey): Sale
    {
        return DB::transaction(function () use ($sale, $deposit, $amountMinor, $idempotencyKey): Sale {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->business_id !== $deposit->business_id || $sale->currency_code !== $deposit->currency_code) {
                throw new DomainException('Deposit cannot be applied to this sale.');
            }
            $amount = min($amountMinor, $sale->balance_minor, $deposit->remainingMinor());
            if ($amount <= 0) {
                return $sale;
            }
            $this->deposits->allocate($deposit, 'apply', $amount, $idempotencyKey, $sale->id);
            $sale->increment('deposit_applied_minor', $amount);
            $sale->decrement('balance_minor', $amount);

            return $sale->fresh();
        });
    }

    /** @param array<string,mixed> $evidence */
    public function recordTender(Sale $sale, string $method, int $amountMinor, string $idempotencyKey, array $evidence = [], ?string $provider = null, ?string $providerReference = null): PaymentTransaction
    {
        return DB::transaction(function () use ($sale, $method, $amountMinor, $idempotencyKey, $evidence, $provider, $providerReference): PaymentTransaction {
            $existing = PaymentTransaction::query()->where('business_id', $sale->business_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== 'open' || $amountMinor <= 0 || $amountMinor > $sale->balance_minor) {
                throw new DomainException('Tender must not exceed the outstanding balance.');
            }
            if (! in_array($method, ['cash', 'card', 'upi', 'bank_transfer', 'payment_link', 'custom', 'pay_later'], true)) {
                throw new DomainException('Unsupported payment method.');
            }
            $transaction = PaymentTransaction::query()->create(['business_id' => $sale->business_id, 'sale_id' => $sale->id, 'appointment_id' => $sale->appointment_id, 'kind' => 'payment', 'method' => $method, 'provider' => $provider, 'provider_reference' => $providerReference, 'idempotency_key' => $idempotencyKey, 'amount_minor' => $amountMinor, 'currency_code' => $sale->currency_code, 'evidence' => $evidence, 'occurred_at' => now()]);
            $sale->increment('paid_minor', $amountMinor);
            $sale->decrement('balance_minor', $amountMinor);
            $sale->refresh();
            if ($sale->balance_minor === 0) {
                $sale->update(['status' => 'completed', 'completed_at' => now()]);
                $sale->refresh();
                $this->inventory->deductCompletedSale($sale);
                $this->commissions->earnForCompletedSale($sale);
                $this->instrumentation->record(Business::query()->findOrFail($sale->business_id), 'checkout.completed', "sale:{$sale->id}:completed", ['location_public_id' => (string) $sale->location_id, 'source' => 'checkout']);
            }

            return $transaction;
        });
    }

    /** @param list<array{sale_line_id:int,amount_minor:int,quantity?:int,disposition?:string}> $lineRefunds */
    public function refund(Sale $sale, PaymentTransaction $original, int $amountMinor, string $idempotencyKey, string $reason, array $lineRefunds = [], string $kind = 'refund'): PaymentTransaction
    {
        return DB::transaction(function () use ($sale, $original, $amountMinor, $idempotencyKey, $reason, $lineRefunds, $kind): PaymentTransaction {
            $existing = PaymentTransaction::query()->where('business_id', $sale->business_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($original->business_id !== $sale->business_id || $original->sale_id !== $sale->id || ! in_array($kind, ['refund', 'void'], true)) {
                throw new DomainException('Refund source does not belong to this sale.');
            }
            $already = PaymentTransaction::query()->where('parent_transaction_id', $original->id)->whereIn('kind', ['refund', 'void'])->sum('amount_minor');
            if ($amountMinor <= 0 || trim($reason) === '' || $amountMinor + $already > $original->amount_minor || $amountMinor + $sale->refunded_minor > $sale->paid_minor) {
                throw new DomainException('Refund exceeds collected tender.');
            }
            $sale->loadMissing('lines');
            if ($sale->lines->where('kind', 'product')->isNotEmpty() && $lineRefunds === []) {
                throw new DomainException('A product refund or void requires an explicit stock disposition.');
            }
            if ($lineRefunds === []) {
                $remaining = $amountMinor;
                foreach ($sale->lines as $line) {
                    $lineValue = max(0, ($line->quantity * $line->unit_price_minor) - $line->discount_minor);
                    $allocated = min($remaining, $lineValue);
                    if ($allocated > 0) {
                        $lineRefunds[] = ['sale_line_id' => $line->id, 'amount_minor' => $allocated, 'quantity' => 0, 'disposition' => 'not_applicable'];
                        $remaining -= $allocated;
                    }
                }
            }
            if (array_sum(array_column($lineRefunds, 'amount_minor')) > $amountMinor) {
                throw new DomainException('Refund line allocations exceed the refund amount.');
            }
            $refund = PaymentTransaction::query()->create(['business_id' => $sale->business_id, 'sale_id' => $sale->id, 'appointment_id' => $sale->appointment_id, 'parent_transaction_id' => $original->id, 'kind' => $kind, 'method' => $original->method, 'provider' => $original->provider, 'idempotency_key' => $idempotencyKey, 'amount_minor' => $amountMinor, 'currency_code' => $sale->currency_code, 'reason' => $reason, 'occurred_at' => now()]);
            foreach ($lineRefunds as $allocation) {
                $line = $sale->lines->firstWhere('id', (int) $allocation['sale_line_id']);
                if (! $line || (int) $allocation['amount_minor'] < 0 || (int) ($allocation['quantity'] ?? 0) > $line->quantity) {
                    throw new DomainException('Refund line allocation is invalid.');
                }
                $disposition = (string) ($allocation['disposition'] ?? ($line->kind === 'product' ? '' : 'not_applicable'));
                DB::table('sale_line_refunds')->insert(['business_id' => $sale->business_id, 'sale_line_id' => $line->id, 'payment_transaction_id' => $refund->id, 'amount_minor' => (int) $allocation['amount_minor'], 'quantity' => (int) ($allocation['quantity'] ?? 0), 'disposition' => $disposition, 'reason' => $reason, 'occurred_at' => $refund->occurred_at, 'created_at' => now(), 'updated_at' => now()]);
                $this->inventory->applyRefundDisposition($line, $refund, (int) ($allocation['quantity'] ?? 0), $disposition, $reason);
            }
            $sale->increment('refunded_minor', $amountMinor);
            $sale->increment('balance_minor', $amountMinor);
            $this->commissions->reverseForRefund($sale, $refund, $lineRefunds);
            $this->instrumentation->record(Business::query()->findOrFail($sale->business_id), 'checkout.refunded', "payment:{$refund->id}:{$kind}", ['location_public_id' => (string) $sale->location_id, 'outcome' => $kind]);

            return $refund;
        });
    }

    public function correctAllocation(PaymentTransaction $original, Sale $targetSale, int $amountMinor, string $idempotencyKey, string $reason): PaymentTransaction
    {
        return DB::transaction(function () use ($original, $targetSale, $amountMinor, $idempotencyKey, $reason): PaymentTransaction {
            $existing = PaymentTransaction::query()->where('business_id', $targetSale->business_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            if ($original->business_id !== $targetSale->business_id || ! $original->sale_id || $amountMinor <= 0) {
                throw new DomainException('Payment allocation correction is not valid.');
            }
            $source = Sale::query()->lockForUpdate()->findOrFail($original->sale_id);
            $target = Sale::query()->lockForUpdate()->findOrFail($targetSale->id);
            $moved = PaymentTransaction::query()->where('parent_transaction_id', $original->id)->where('kind', 'correction')->sum('amount_minor');
            if ($moved + $amountMinor > $original->amount_minor) {
                throw new DomainException('Correction exceeds the original payment.');
            }
            $correction = PaymentTransaction::query()->create(['business_id' => $target->business_id, 'sale_id' => $target->id, 'appointment_id' => $target->appointment_id, 'parent_transaction_id' => $original->id, 'kind' => 'correction', 'method' => $original->method, 'idempotency_key' => $idempotencyKey, 'amount_minor' => $amountMinor, 'currency_code' => $target->currency_code, 'reason' => $reason, 'occurred_at' => now()]);
            $source->decrement('paid_minor', $amountMinor);
            $source->increment('balance_minor', $amountMinor);
            $target->increment('paid_minor', $amountMinor);
            $target->decrement('balance_minor', $amountMinor);

            return $correction;
        });
    }
}
