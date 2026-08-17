<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\MoneyCommerce\Models\CashClose;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\PlatformAccess\Models\Location;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class CashCloseService
{
    /** @return array<string,mixed> */
    public function summary(Location $location, CarbonImmutable $businessDate, int $openingCashMinor): array
    {
        $start = $businessDate->setTimezone($location->time_zone)->startOfDay()->utc();
        $end = $start->addDay();
        $transactions = PaymentTransaction::query()->where('business_id', $location->business_id)->whereBetween('occurred_at', [$start, $end])->whereHas('sale', fn ($q) => $q->where('location_id', $location->id))->get();
        $methods = [];
        foreach ($transactions as $transaction) {
            $methods[$transaction->method] = ($methods[$transaction->method] ?? 0) + (in_array($transaction->kind, ['refund', 'void'], true) ? -$transaction->amount_minor : $transaction->amount_minor);
        }
        $cashNet = $methods['cash'] ?? 0;
        $outstanding = Sale::query()->where('business_id', $location->business_id)->where('location_id', $location->id)->where('status', 'open')->sum('balance_minor');

        return ['method_summary' => $methods, 'expected_cash_minor' => $openingCashMinor + $cashNet, 'outstanding_balance_minor' => (int) $outstanding];
    }

    public function close(Location $location, CarbonImmutable $businessDate, int $openingCashMinor, int $actualCashMinor, ?string $varianceReason, int $membershipId): CashClose
    {
        return DB::transaction(function () use ($location, $businessDate, $openingCashMinor, $actualCashMinor, $varianceReason, $membershipId): CashClose {
            $existing = CashClose::query()->where('business_id', $location->business_id)->where('location_id', $location->id)->where('business_date', $businessDate->toDateString())->lockForUpdate()->first();
            if ($existing) {
                throw new DomainException('This cash drawer is already closed. Use a controlled adjustment.');
            }
            $summary = $this->summary($location, $businessDate, $openingCashMinor);
            $variance = $actualCashMinor - $summary['expected_cash_minor'];
            if ($variance !== 0 && blank($varianceReason)) {
                throw new DomainException('A cash variance needs a reason.');
            }

            return CashClose::query()->create(['business_id' => $location->business_id, 'location_id' => $location->id, 'business_date' => $businessDate->toDateString(), 'currency_code' => $location->business->currency_code, 'opening_cash_minor' => $openingCashMinor, 'expected_cash_minor' => $summary['expected_cash_minor'], 'actual_cash_minor' => $actualCashMinor, 'variance_minor' => $variance, 'variance_reason' => $varianceReason, 'method_summary' => $summary['method_summary'], 'outstanding_balance_minor' => $summary['outstanding_balance_minor'], 'closed_by_membership_id' => $membershipId, 'closed_at' => now()]);
        });
    }

    public function adjust(CashClose $close, int $amountMinor, string $reason, int $approverMembershipId): void
    {
        if ($amountMinor === 0 || blank($reason)) {
            throw new DomainException('A post-close adjustment needs an amount and reason.');
        }
        DB::table('cash_close_adjustments')->insert(['business_id' => $close->business_id, 'cash_close_id' => $close->id, 'amount_minor' => $amountMinor, 'reason' => $reason, 'approved_by_membership_id' => $approverMembershipId, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
