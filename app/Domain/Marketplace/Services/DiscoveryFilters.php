<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class DiscoveryFilters
{
    public function match(Business $business, array $filters, ?array $point = null): ?array
    {
        $catalog = app(PublicBookingService::class)->catalog($business);
        $prices = [];
        $next = null;
        $max = isset($filters['max_price']) && $filters['max_price'] !== null ? (int) round($filters['max_price'] * 100) : null;
        foreach ($catalog['locations'] as $location) {
            if (isset($filters['_location']) && $location['public_id'] !== $filters['_location']) { continue; }
            if (! empty($filters['location']) && ! str_contains(mb_strtolower((string) $location['address']), mb_strtolower($filters['location']))) {
                continue;
            }
            if ($point) {
                if (! isset($location['entrance_latitude'],$location['entrance_longitude'])) {
                    continue;
                }
                $distance = sqrt((($location['entrance_latitude'] - $point[0]) * 111.32) ** 2 + (($location['entrance_longitude'] - $point[1]) * 111.32 * cos(deg2rad($point[0]))) ** 2);
                if ($distance > 25) {
                    continue;
                }
            }
            foreach ($catalog['services'] as $service) {
                if (isset($filters['_service']) && $service['public_id'] !== $filters['_service']) { continue; }
                if ($service['kind'] !== 'service' || ! in_array($location['public_id'], $service['location_ids'], true)) {
                    continue;
                }
                if (! empty($filters['q']) && ! str_contains(mb_strtolower($business->name), mb_strtolower($filters['q'])) && ! str_contains(mb_strtolower($service['name']), mb_strtolower($filters['q']))) {
                    continue;
                }
                if ($max !== null && $service['currency_code'] !== $filters['currency']) {
                    continue;
                }
                $eligible = collect($catalog['staff'])->filter(fn ($person) => in_array($location['public_id'], $person['location_ids'], true) && in_array($service['public_id'], $person['service_ids'], true));
                $branchPrice = collect($service['location_prices'])->firstWhere('location', $location['public_id'])['price_minor'] ?? null;
                $amounts = $eligible->map(fn ($person) => collect($person['service_variants'])->firstWhere('service', $service['public_id'])['price_minor'] ?? $branchPrice ?? $service['price_minor']);
                if ($amounts->isEmpty() || ($max !== null && $amounts->min() > $max)) {
                    continue;
                }
                if (! empty($filters['date'])) {
                    $date = $filters['date'] === 'today' ? CarbonImmutable::now($location['time_zone'])->toDateString() : $filters['date'];
                    try {
                        $slots = app(PublicBookingService::class)->search($business, $location['public_id'], [$service['public_id']], null, $date, $date, 'auto');
                    } catch (BookingRuleViolation|ValidationException $exception) {
                        continue;
                    }
                    $slot = collect($slots)->first(fn ($slot) => $max === null || ($slot['currency_code'] === $filters['currency'] && $slot['price_minor'] <= $max));
                    if (! $slot) {
                        continue;
                    }
                    if (! $next || $slot['starts_at_utc'] < $next['starts_at_utc']) {
                        $next = ['starts_at_utc' => $slot['starts_at_utc'], 'time_zone' => $slot['time_zone']];
                    }
                }
                $prices[] = ['amount' => $amounts->min(), 'currency' => $service['currency_code']];
            }
        }
        if (! $prices) {
            return null;
        }

        return ['next_time' => $next];
    }
}
