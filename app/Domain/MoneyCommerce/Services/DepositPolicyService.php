<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Money\MoneyCalculator;

class DepositPolicyService
{
    public function __construct(private readonly MoneyCalculator $money) {}

    /** @param list<array<string,mixed>> $serviceSnapshots @return array<string,mixed> */
    public function resolve(Business $business, array $serviceSnapshots, string $clientEligibility = 'existing', ?int $clientId = null): array
    {
        $settings = $this->settings($business);
        $serviceTotal = array_sum(array_map(fn (array $line) => (int) ($line['priceMinor'] ?? $line['price_minor'] ?? 0), $serviceSnapshots));
        $priorNoShows = $clientId ? Appointment::query()->where('business_id', $business->id)->where('client_id', $clientId)->where('status', 'no_show')->count() : 0;
        $hasServiceRule = collect($serviceSnapshots)->contains(fn (array $line) => ($line['depositType'] ?? $line['deposit_type'] ?? 'none') !== 'none');
        $applies = $hasServiceRule || (! $settings->deposit_new_clients_only || $clientEligibility === 'new')
            && ($settings->deposit_threshold_minor === 0 || $serviceTotal >= $settings->deposit_threshold_minor)
            && ($settings->deposit_prior_no_show_count === 0 || $priorNoShows >= $settings->deposit_prior_no_show_count);
        $amount = 0;
        if ($applies && $hasServiceRule) {
            foreach ($serviceSnapshots as $line) {
                $price = (int) ($line['priceMinor'] ?? $line['price_minor'] ?? 0);
                $type = $line['depositType'] ?? $line['deposit_type'] ?? 'none';
                $value = (int) ($line['depositValue'] ?? $line['deposit_value'] ?? 0);
                $amount += match ($type) {
                    'fixed' => min($price, $value), 'percentage' => $this->money->percentageOf($price, $value), 'full' => $price, default => 0
                };
            }
        } elseif ($applies) {
            $amount = match ($settings->default_deposit_type) {
                'fixed' => min($serviceTotal, $settings->default_deposit_value), 'percentage' => $this->money->percentageOf($serviceTotal, $settings->default_deposit_value), 'full' => $serviceTotal, default => 0
            };
        }

        return ['type' => $hasServiceRule ? 'service' : $settings->default_deposit_type, 'amount_minor' => min($serviceTotal, $amount), 'currency_code' => $settings->currency_code, 'client_eligibility' => $clientEligibility, 'service_total_minor' => $serviceTotal, 'prior_no_show_count' => $priorNoShows, 'cancellation_cutoff_minutes' => $settings->cancellation_cutoff_minutes, 'deposit_refundable_before_cutoff' => $settings->deposit_refundable_before_cutoff, 'cancellation_fee_minor' => $settings->cancellation_fee_minor, 'no_show_fee_minor' => $settings->no_show_fee_minor, 'resolved_at' => now()->utc()->toIso8601String()];
    }

    public function settings(Business $business): CommerceSetting
    {
        return CommerceSetting::query()->firstOrCreate(['business_id' => $business->id], ['currency_code' => $business->currency_code, 'cancellation_cutoff_minutes' => max(0, (int) $business->cancellation_cutoff_minutes)]);
    }
}
