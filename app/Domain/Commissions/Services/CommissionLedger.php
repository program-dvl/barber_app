<?php

namespace App\Domain\Commissions\Services;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\Commissions\Models\CommissionEntry;
use App\Domain\Commissions\Models\CommissionRule;
use App\Domain\Commissions\Models\TipEntry;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleLine;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class CommissionLedger
{
    public function createRule(array $attributes): CommissionRule
    {
        $businessId = (int) ($attributes['business_id'] ?? 0);
        $kind = $attributes['kind'] ?? null;
        if (! in_array($kind, ['service_percentage', 'product_percentage', 'fixed_service'], true)) {
            throw new DomainException('Unsupported commission rule type.');
        }
        $hasRate = isset($attributes['rate_bps']) && (int) $attributes['rate_bps'] >= 0 && (int) $attributes['rate_bps'] <= 10000;
        $hasAmount = isset($attributes['amount_minor']) && (int) $attributes['amount_minor'] >= 0;
        if (($kind === 'fixed_service' && ! $hasAmount) || ($kind !== 'fixed_service' && ! $hasRate)) {
            throw new DomainException('Commission rule value does not match its type.');
        }
        if ($kind === 'fixed_service' && empty($attributes['service_id'])) {
            throw new DomainException('A fixed-service rule requires a service.');
        }
        if (! empty($attributes['staff_profile_id']) && ! StaffProfile::query()->forBusiness($businessId)->whereKey($attributes['staff_profile_id'])->exists()) {
            throw new DomainException('Commission rule staff must belong to the business.');
        }
        if (! empty($attributes['service_id']) && ! Service::query()->forBusiness($businessId)->whereKey($attributes['service_id'])->exists()) {
            throw new DomainException('Commission rule service must belong to the business.');
        }

        return CommissionRule::query()->create($attributes);
    }

    public function earnForCompletedSale(Sale $sale): void
    {
        $sale->loadMissing('lines');
        $at = $sale->completed_at ?? now();
        foreach ($sale->lines as $line) {
            if (! $line->staff_profile_id || ! in_array($line->kind, ['service', 'product'], true)) {
                continue;
            }
            $rule = $this->effectiveRule($sale, $line, $at);
            if (! $rule) {
                continue;
            }
            $base = max(0, ($line->quantity * $line->unit_price_minor) - $line->discount_minor);
            $amount = $rule->kind === 'fixed_service'
                ? $rule->amount_minor * $line->quantity
                : (int) round($base * $rule->rate_bps / 10000, 0, PHP_ROUND_HALF_UP);
            CommissionEntry::query()->firstOrCreate(
                ['business_id' => $sale->business_id, 'idempotency_key' => "sale:{$sale->id}:line:{$line->id}:earned"],
                ['staff_profile_id' => $line->staff_profile_id, 'sale_line_id' => $line->id, 'commission_rule_id' => $rule->id, 'type' => 'earned', 'base_minor' => $base, 'rate_bps' => $rule->rate_bps, 'amount_minor' => $amount, 'currency_code' => $sale->currency_code, 'occurred_at' => $at],
            );
        }

        $tips = DB::table('sale_tip_allocations')->where('business_id', $sale->business_id)->where('sale_id', $sale->id)->get();
        foreach ($tips as $tip) {
            if (! $tip->staff_profile_id || $tip->amount_minor <= 0) {
                continue;
            }
            TipEntry::query()->firstOrCreate(
                ['business_id' => $sale->business_id, 'idempotency_key' => "sale:{$sale->id}:tip:{$tip->id}:earned"],
                ['sale_id' => $sale->id, 'staff_profile_id' => $tip->staff_profile_id, 'type' => 'earned', 'amount_minor' => $tip->amount_minor, 'currency_code' => $sale->currency_code, 'occurred_at' => $at],
            );
        }
    }

    /** @param list<array{sale_line_id:int,amount_minor:int,quantity:int,disposition:string}> $lineRefunds */
    public function reverseForRefund(Sale $sale, PaymentTransaction $refund, array $lineRefunds): void
    {
        foreach ($lineRefunds as $allocation) {
            $earned = CommissionEntry::query()->forBusiness($sale->business_id)
                ->where('sale_line_id', $allocation['sale_line_id'])->where('type', 'earned')->get();
            foreach ($earned as $entry) {
                $baseReversed = min($entry->base_minor, (int) $allocation['amount_minor']);
                $amount = $entry->base_minor > 0
                    ? (int) round($entry->amount_minor * $baseReversed / $entry->base_minor, 0, PHP_ROUND_HALF_UP)
                    : $entry->amount_minor;
                $already = abs((int) CommissionEntry::query()->forBusiness($sale->business_id)->where('sale_line_id', $entry->sale_line_id)->whereIn('type', ['refund_reversal', 'void_reversal'])->sum('amount_minor'));
                $amount = min(max(0, $entry->amount_minor - $already), $amount);
                if ($amount === 0) {
                    continue;
                }
                CommissionEntry::query()->firstOrCreate(
                    ['business_id' => $sale->business_id, 'idempotency_key' => "refund:{$refund->id}:commission:{$entry->id}"],
                    ['staff_profile_id' => $entry->staff_profile_id, 'sale_line_id' => $entry->sale_line_id, 'commission_rule_id' => $entry->commission_rule_id, 'payment_transaction_id' => $refund->id, 'type' => $refund->kind === 'void' ? 'void_reversal' : 'refund_reversal', 'base_minor' => -$baseReversed, 'rate_bps' => $entry->rate_bps, 'amount_minor' => -$amount, 'currency_code' => $sale->currency_code, 'reason' => $refund->reason, 'occurred_at' => $refund->occurred_at],
                );
            }
        }

        $ratio = $sale->total_minor > 0 ? min(1, $refund->amount_minor / $sale->total_minor) : 0;
        TipEntry::query()->forBusiness($sale->business_id)->where('sale_id', $sale->id)->where('type', 'earned')->each(function (TipEntry $tip) use ($refund, $ratio, $sale): void {
            $already = abs((int) TipEntry::query()->forBusiness($sale->business_id)->where('sale_id', $sale->id)->where('staff_profile_id', $tip->staff_profile_id)->whereIn('type', ['refund_reversal', 'void_reversal'])->sum('amount_minor'));
            $amount = min(max(0, $tip->amount_minor - $already), (int) round($tip->amount_minor * $ratio, 0, PHP_ROUND_HALF_UP));
            if ($amount > 0) {
                TipEntry::query()->firstOrCreate(
                    ['business_id' => $sale->business_id, 'idempotency_key' => "refund:{$refund->id}:tip:{$tip->id}"],
                    ['sale_id' => $sale->id, 'staff_profile_id' => $tip->staff_profile_id, 'payment_transaction_id' => $refund->id, 'type' => $refund->kind === 'void' ? 'void_reversal' : 'refund_reversal', 'amount_minor' => -$amount, 'currency_code' => $sale->currency_code, 'reason' => $refund->reason, 'occurred_at' => $refund->occurred_at],
                );
            }
        });
    }

    public function adjustCommission(StaffProfile $staff, int $amountMinor, string $currency, Membership $manager, string $reason, string $idempotencyKey): CommissionEntry
    {
        $this->assertAdjustment($staff, $manager, $amountMinor, $reason);

        return CommissionEntry::query()->firstOrCreate(
            ['business_id' => $staff->business_id, 'idempotency_key' => $idempotencyKey],
            ['staff_profile_id' => $staff->id, 'approved_by_membership_id' => $manager->id, 'type' => 'manager_adjustment', 'amount_minor' => $amountMinor, 'currency_code' => $currency, 'reason' => $reason, 'occurred_at' => now()],
        );
    }

    public function adjustTip(StaffProfile $staff, int $amountMinor, string $currency, Membership $manager, string $reason, string $idempotencyKey): TipEntry
    {
        $this->assertAdjustment($staff, $manager, $amountMinor, $reason);

        return TipEntry::query()->firstOrCreate(
            ['business_id' => $staff->business_id, 'idempotency_key' => $idempotencyKey],
            ['staff_profile_id' => $staff->id, 'approved_by_membership_id' => $manager->id, 'type' => 'manager_adjustment', 'amount_minor' => $amountMinor, 'currency_code' => $currency, 'reason' => $reason, 'occurred_at' => now()],
        );
    }

    /** @return array{entries:array<int,array<string,mixed>>,commission_minor:int,tips_minor:int,total_minor:int} */
    public function statement(int $businessId, int $staffId, CarbonInterface $from, CarbonInterface $to): array
    {
        $commissions = CommissionEntry::query()->forBusiness($businessId)->where('staff_profile_id', $staffId)->whereBetween('occurred_at', [$from, $to])->with('business')->orderBy('occurred_at')->get();
        $tips = TipEntry::query()->forBusiness($businessId)->where('staff_profile_id', $staffId)->whereBetween('occurred_at', [$from, $to])->orderBy('occurred_at')->get();
        $entries = $commissions->map(fn ($entry) => ['source' => 'commission', 'source_id' => $entry->id, 'sale_line_id' => $entry->sale_line_id, 'payment_transaction_id' => $entry->payment_transaction_id, 'type' => $entry->type, 'amount_minor' => $entry->amount_minor, 'occurred_at' => $entry->occurred_at->toIso8601String(), 'reason' => $entry->reason])
            ->concat($tips->map(fn ($entry) => ['source' => 'tip', 'source_id' => $entry->id, 'sale_line_id' => null, 'payment_transaction_id' => $entry->payment_transaction_id, 'type' => $entry->type, 'amount_minor' => $entry->amount_minor, 'occurred_at' => $entry->occurred_at->toIso8601String(), 'reason' => $entry->reason]))
            ->sortBy('occurred_at')->values()->all();

        return ['entries' => $entries, 'commission_minor' => (int) $commissions->sum('amount_minor'), 'tips_minor' => (int) $tips->sum('amount_minor'), 'total_minor' => (int) $commissions->sum('amount_minor') + (int) $tips->sum('amount_minor')];
    }

    private function effectiveRule(Sale $sale, SaleLine $line, CarbonInterface $at): ?CommissionRule
    {
        $kind = $line->kind === 'product' ? 'product_percentage' : null;
        $query = CommissionRule::query()->forBusiness($sale->business_id)
            ->where(fn ($q) => $q->whereNull('staff_profile_id')->orWhere('staff_profile_id', $line->staff_profile_id))
            ->where('effective_from', '<=', $at)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at));
        if ($kind) {
            $query->where('kind', $kind);
        } else {
            $serviceId = (int) data_get($line->source_snapshot, 'service_id', 0);
            $query->whereIn('kind', ['fixed_service', 'service_percentage'])
                ->where(fn ($q) => $q->whereNull('service_id')->orWhere('service_id', $serviceId));
        }

        return $query->orderByRaw('staff_profile_id is null')->orderByRaw('service_id is null')->orderByRaw("case when kind = 'fixed_service' then 0 else 1 end")->latest('effective_from')->first();
    }

    private function assertAdjustment(StaffProfile $staff, Membership $manager, int $amountMinor, string $reason): void
    {
        if ($staff->business_id !== $manager->business_id || $amountMinor === 0 || trim($reason) === '') {
            throw new DomainException('A manager adjustment requires matching tenant, non-zero amount, and reason.');
        }
    }
}
