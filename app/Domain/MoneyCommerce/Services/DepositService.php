<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\MoneyCommerce\Models\Deposit;
use App\Domain\MoneyCommerce\Models\DepositAllocation;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\SchedulingOperations\Models\Appointment;
use DomainException;
use Illuminate\Support\Facades\DB;

class DepositService
{
    /** @param array<string,mixed> $policy */
    public function bind(Appointment $appointment, PaymentTransaction $payment, array $policy): Deposit
    {
        return DB::transaction(function () use ($appointment, $payment, $policy): Deposit {
            $existing = Deposit::query()->where('payment_transaction_id', $payment->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            if ($payment->amount_minor !== (int) $policy['amount_minor'] || $payment->currency_code !== $policy['currency_code']) {
                throw new DomainException('Deposit payment does not match its displayed policy.');
            }

            return Deposit::query()->create(['business_id' => $appointment->business_id, 'appointment_id' => $appointment->id, 'client_id' => $appointment->client_id, 'payment_transaction_id' => $payment->id, 'original_amount_minor' => $payment->amount_minor, 'currency_code' => $payment->currency_code, 'policy_snapshot' => $policy]);
        });
    }

    public function allocate(Deposit $deposit, string $action, int $amountMinor, string $idempotencyKey, ?int $saleId = null, ?PaymentTransaction $transaction = null, ?string $reason = null, ?int $appointmentId = null): DepositAllocation
    {
        return DB::transaction(function () use ($deposit, $action, $amountMinor, $idempotencyKey, $saleId, $transaction, $reason, $appointmentId): DepositAllocation {
            $existing = DepositAllocation::query()->where('business_id', $deposit->business_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $locked = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);
            if ($amountMinor <= 0 || $amountMinor > $locked->remainingMinor()) {
                throw new DomainException('Deposit allocation exceeds the unallocated deposit.');
            }
            if (! in_array($action, ['apply', 'transfer', 'refund', 'forfeit', 'credit'], true)) {
                throw new DomainException('Invalid deposit action.');
            }
            $column = match ($action) {
                'apply' => 'applied_minor', 'refund' => 'refunded_minor', 'forfeit' => 'forfeited_minor', 'credit', 'transfer' => 'credited_minor'
            };
            $locked->increment($column, $amountMinor);
            $locked->refresh();
            $locked->update(['status' => $locked->remainingMinor() === 0 ? 'settled' : 'bound']);

            return DepositAllocation::query()->create(['business_id' => $locked->business_id, 'deposit_id' => $locked->id, 'appointment_id' => $appointmentId ?? $locked->appointment_id, 'sale_id' => $saleId, 'payment_transaction_id' => $transaction?->id, 'action' => $action, 'amount_minor' => $amountMinor, 'idempotency_key' => $idempotencyKey, 'reason' => $reason, 'evidence' => ['deposit_remaining_minor' => $locked->remainingMinor()], 'occurred_at' => now()]);
        });
    }

    public function transfer(Deposit $deposit, Appointment $replacement, string $idempotencyKey, string $reason): DepositAllocation
    {
        if ($deposit->business_id !== $replacement->business_id || $deposit->currency_code !== $replacement->currency_code) {
            throw new DomainException('A deposit transfer must remain in its business and currency.');
        }

        return $this->allocate($deposit, 'transfer', $deposit->remainingMinor(), $idempotencyKey, reason: $reason, appointmentId: $replacement->id);
    }

    public function settleCancellation(Deposit $deposit, bool $beforeCutoff, bool $isNoShow, bool $managerWaived, string $idempotencyKey, string $reason): ?DepositAllocation
    {
        if ($managerWaived) {
            return null;
        }
        $policy = $deposit->policy_snapshot;
        $action = $beforeCutoff && ($policy['deposit_refundable_before_cutoff'] ?? false) ? 'refund' : 'forfeit';
        $fee = $isNoShow ? (int) ($policy['no_show_fee_minor'] ?? 0) : (int) ($policy['cancellation_fee_minor'] ?? 0);
        $amount = $action === 'refund' ? $deposit->remainingMinor() : min($deposit->remainingMinor(), max($fee, $deposit->remainingMinor()));

        return $amount > 0 ? $this->allocate($deposit, $action, $amount, $idempotencyKey, reason: $reason) : null;
    }
}
