<?php

namespace App\Support\Money;

use DomainException;

/** The sole integer-minor-unit calculator for salon sales. */
class MoneyCalculator
{
    /** @param list<array{quantity:int,unit_price_minor:int,tax_rate_bps?:int,discount_minor?:int,description?:string}> $lines
     * @return array<string, mixed> */
    public function calculate(array $lines, string $currencyCode, bool $taxInclusive, int $tipMinor = 0): array
    {
        if ($lines === [] || $tipMinor < 0) {
            throw new DomainException('A sale needs at least one non-negative line and tip.');
        }
        $subtotal = $discount = $tax = 0;
        $calculated = [];
        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            $unit = (int) $line['unit_price_minor'];
            $lineDiscount = (int) ($line['discount_minor'] ?? 0);
            $rate = (int) ($line['tax_rate_bps'] ?? 0);
            if ($quantity < 1 || $unit < 0 || $lineDiscount < 0 || $rate < 0 || $rate > 100000) {
                throw new DomainException('Invalid monetary line.');
            }
            $gross = $quantity * $unit;
            if ($lineDiscount > $gross) {
                throw new DomainException('A discount cannot exceed its line value.');
            }
            $net = $gross - $lineDiscount;
            $lineTax = $taxInclusive
                ? $net - $this->roundDivide($net * 10000, 10000 + $rate)
                : $this->roundDivide($net * $rate, 10000);
            $subtotal += $gross;
            $discount += $lineDiscount;
            $tax += $lineTax;
            $calculated[] = [...$line, 'gross_minor' => $gross, 'net_before_tax_minor' => $net, 'tax_minor' => $lineTax, 'line_total_minor' => $taxInclusive ? $net : $net + $lineTax];
        }
        $lineTotal = $taxInclusive ? $subtotal - $discount : $subtotal - $discount + $tax;

        return ['currency_code' => strtoupper($currencyCode), 'tax_inclusive' => $taxInclusive, 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'tax_minor' => $tax, 'tip_minor' => $tipMinor, 'total_minor' => $lineTotal + $tipMinor, 'lines' => $calculated, 'rounding' => 'half_up_per_line'];
    }

    public function percentageOf(int $amountMinor, int $basisPoints): int
    {
        if ($amountMinor < 0 || $basisPoints < 0 || $basisPoints > 10000) {
            throw new DomainException('Invalid percentage calculation.');
        }

        return $this->roundDivide($amountMinor * $basisPoints, 10000);
    }

    private function roundDivide(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
