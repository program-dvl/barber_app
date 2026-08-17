<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Data\EffectiveService;
use App\Domain\BusinessConfiguration\Models\ConfigurationSnapshot;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class EffectiveServiceResolver
{
    public function resolve(Service $service, StaffProfile $staff, Location $location, ?CarbonImmutable $at = null): EffectiveService
    {
        $at ??= CarbonImmutable::now();
        if (count(array_unique([$service->business_id, $staff->business_id, $location->business_id])) !== 1) {
            throw ValidationException::withMessages(['service' => 'Service, staff, and location must belong to the same business.']);
        }

        $locationRule = $service->locations()->whereKey($location->getKey())->wherePivot('is_eligible', true)->first();
        $assignment = $service->staffAssignments()
            ->where('staff_profile_id', $staff->getKey())->where('is_active', true)->where('is_qualified', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $at))
            ->latest('effective_from')->first();

        $serviceIsEffective = (! $service->effective_from || $service->effective_from <= $at)
            && (! $service->effective_until || $service->effective_until > $at);
        if (! $service->is_active || ! $serviceIsEffective || ! $location->is_active || $location->status !== 'active' || ! $locationRule || ! $assignment || $staff->status !== 'active' || ! $staff->locations()->whereKey($location->getKey())->exists()) {
            throw ValidationException::withMessages(['service' => 'This service, staff, and location combination is not eligible.']);
        }

        $price = $assignment->price_minor ?? $locationRule->pivot->price_minor ?? $service->price_minor;
        $duration = $assignment->duration_minutes ?? $service->duration_minutes;
        $processing = $assignment->processing_minutes ?? $service->processing_minutes;
        $cleanup = $assignment->cleanup_minutes ?? $service->cleanup_minutes;
        $segments = $service->segments()->get()->map(fn ($segment) => [
            'id' => $segment->id, 'kind' => $segment->kind, 'sequence' => $segment->sequence,
            'duration_minutes' => $segment->duration_minutes, 'occupies_staff' => $segment->occupies_staff,
        ])->all();
        if ($segments === []) {
            $segments = array_values(array_filter([
                $duration > 0 ? ['id' => null, 'kind' => 'active', 'sequence' => 1, 'duration_minutes' => $duration, 'occupies_staff' => true] : null,
                $processing > 0 ? ['id' => null, 'kind' => 'processing', 'sequence' => 2, 'duration_minutes' => $processing, 'occupies_staff' => false] : null,
                $cleanup > 0 ? ['id' => null, 'kind' => 'cleanup', 'sequence' => 3, 'duration_minutes' => $cleanup, 'occupies_staff' => true] : null,
            ]));
        } else {
            $segments = $this->redistributeSegmentKind($segments, 'active', $duration);
            $segments = $this->redistributeSegmentKind($segments, 'processing', $processing);
            $segments = $this->redistributeSegmentKind($segments, 'cleanup', $cleanup);
        }
        $depositMinor = match ($service->deposit_type) {
            'fixed' => min($price, $service->deposit_value),
            'percentage' => intdiv(($price * $service->deposit_value) + 9999, 10000),
            default => 0,
        };

        return new EffectiveService(
            $service->id, $staff->id, $location->id, $service->name, $service->price_type, $price, $service->currency_code,
            $duration, $processing, $cleanup, $duration + $processing + $cleanup,
            (string) $service->tax_category, $service->tax_inclusive, $service->deposit_type,
            $service->deposit_value, $depositMinor, $service->online_visible && $assignment->online_visible,
            $service->client_eligibility, $service->consultation_required, $service->minimum_notice_minutes,
            $service->maximum_advance_days, $assignment->commission_rate, $segments, $at->utc()->toIso8601String(),
        );
    }

    public function capture(EffectiveService $resolved, string $snapshotType = 'appointment_service_line'): ConfigurationSnapshot
    {
        return ConfigurationSnapshot::query()->create([
            'business_id' => Service::query()->findOrFail($resolved->serviceId)->business_id,
            'snapshot_type' => $snapshotType,
            'subject_type' => Service::class,
            'subject_id' => $resolved->serviceId,
            'values' => $resolved->snapshot(),
            'effective_at' => $resolved->resolvedAt,
            'captured_at' => now(),
        ]);
    }

    /**
     * Staff variants store aggregate active/processing/cleanup durations. When
     * a Service has explicit segments, preserve their sequence and occupancy
     * while distributing that effective aggregate across same-kind segments.
     *
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    private function redistributeSegmentKind(array $segments, string $kind, int $targetMinutes): array
    {
        $indexes = array_keys(array_filter($segments, fn (array $segment) => $segment['kind'] === $kind));
        if ($indexes === []) {
            return $segments;
        }
        $baseTotal = array_sum(array_map(fn (int $index) => (int) $segments[$index]['duration_minutes'], $indexes));
        $remaining = $targetMinutes;
        foreach ($indexes as $position => $index) {
            $isLast = $position === array_key_last($indexes);
            $minutes = $isLast
                ? $remaining
                : ($baseTotal > 0 ? intdiv($targetMinutes * (int) $segments[$index]['duration_minutes'], $baseTotal) : 0);
            $segments[$index]['duration_minutes'] = $minutes;
            $remaining -= $minutes;
        }

        return array_values(array_filter($segments, fn (array $segment) => (int) $segment['duration_minutes'] > 0));
    }
}
