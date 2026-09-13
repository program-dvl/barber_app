<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessActivationManager
{
    public function __construct(
        private readonly EntitlementEvaluator $entitlements,
        private readonly OnboardingManager $onboarding,
        private readonly StaffScheduleValidator $scheduleValidator,
        private readonly AuditWriter $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function saveLocation(Business $business, Membership $membership, array $data): Location
    {
        $location = DB::transaction(function () use ($business, $membership, $data): Location {
            $lockedBusiness = Business::query()->lockForUpdate()->findOrFail($business->getKey());
            $createNew = (bool) ($data['create_new'] ?? false);
            $location = filled($data['location'] ?? null)
                ? $lockedBusiness->locations()->where('public_id', $data['location'])->firstOrFail()
                : ($createNew ? null : $lockedBusiness->locations()->oldest('id')->first());

            if (! $location) {
                $this->entitlements->authorize($lockedBusiness, 'locations.max', 'create', 1);
                $location = new Location(['business_id' => $lockedBusiness->getKey()]);
            }

            $locationName = trim($data['name']);
            $duplicateName = $lockedBusiness->locations()
                ->whereRaw('lower(name) = ?', [mb_strtolower($locationName)])
                ->when($location->exists, fn ($query) => $query->whereKeyNot($location->getKey()))
                ->exists();
            if ($duplicateName) {
                throw ValidationException::withMessages([
                    'name' => 'Use a different location name so clients and team members can tell each place apart.',
                ]);
            }
            $data['name'] = $locationName;

            if ($location->exists && $lockedBusiness->configuration_published_at
                && $this->storedLocationWindows($location) !== $this->requestedWindows($data['working_days'], $data['opens_at'], $data['closes_at'])) {
                throw ValidationException::withMessages([
                    'working_days' => 'Published opening-hour changes require an appointment impact preview before they can be applied.',
                ]);
            }

            $before = $location->exists ? $location->only(['name', 'address', 'time_zone', 'phone', 'email']) : [];
            $location->fill(Arr::only($data, ['name', 'address', 'time_zone', 'phone', 'email']));
            $location->forceFill(['status' => 'active', 'is_active' => true])->save();
            $membership->locations()->syncWithoutDetaching([
                $location->getKey() => ['business_id' => $lockedBusiness->getKey()],
            ]);

            $location->hours()->delete();
            foreach (array_values(array_unique($data['working_days'])) as $day) {
                LocationHour::query()->create([
                    'business_id' => $lockedBusiness->getKey(),
                    'location_id' => $location->getKey(),
                    'day_of_week' => $day,
                    'opens_at' => $data['opens_at'],
                    'closes_at' => $data['closes_at'],
                    'sequence' => 1,
                ]);
            }

            $this->audit->write(
                'activation.location.saved',
                $lockedBusiness,
                target: $location,
                before: $before,
                after: [
                    ...$location->only(['name', 'address', 'time_zone', 'phone', 'email']),
                    'working_days' => array_values(array_unique($data['working_days'])),
                    'opens_at' => $data['opens_at'],
                    'closes_at' => $data['closes_at'],
                ],
            );
            $this->onboarding->saveStep($lockedBusiness, 'hours');

            return $location;
        }, 3);

        return $location->fresh(['hours']);
    }

    /** @param array<string, mixed> $data */
    public function saveProvider(Business $business, array $data): StaffProfile
    {
        $rules = collect(array_values(array_unique($data['working_days'])))->map(fn (int $day): array => [
            'kind' => 'working',
            'location_id' => (int) $data['location_id'],
            'day_of_week' => $day,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'sequence' => 1,
        ])->all();
        $this->scheduleValidator->validate($rules);

        $staff = DB::transaction(function () use ($business, $data, $rules): StaffProfile {
            $lockedBusiness = Business::query()->lockForUpdate()->findOrFail($business->getKey());
            $location = $lockedBusiness->locations()->whereKey($data['location_id'])->firstOrFail();
            $email = strtolower(trim($data['email']));
            $staff = $lockedBusiness->staffProfiles()->whereRaw('lower(email) = ?', [$email])->lockForUpdate()->first();
            if (! $staff) {
                $this->entitlements->authorize($lockedBusiness, 'staff.max', 'create', 1);
                $staff = new StaffProfile(['business_id' => $lockedBusiness->getKey()]);
            }

            if ($staff->exists && $lockedBusiness->configuration_published_at
                && $this->storedStaffWindows($staff) !== $this->requestedStaffWindows($rules)) {
                throw ValidationException::withMessages([
                    'working_days' => 'Published provider schedule changes require an appointment impact preview before they can be applied.',
                ]);
            }

            $before = $staff->exists ? $staff->only(['display_name', 'email', 'mobile', 'title', 'online_visible', 'status']) : [];
            $staff->fill([
                'display_name' => trim($data['display_name']),
                'email' => $email,
                'mobile' => $data['mobile'] ?? null,
                'title' => $data['title'] ?? null,
                'online_visible' => (bool) ($data['online_visible'] ?? true),
                'status' => 'active',
            ])->save();
            $staff->locations()->syncWithoutDetaching([
                $location->getKey() => ['business_id' => $lockedBusiness->getKey()],
            ]);
            $staff->availabilityRules()->where('kind', 'working')->delete();
            foreach ($rules as $rule) {
                StaffAvailabilityRule::query()->create([
                    ...$rule,
                    'business_id' => $lockedBusiness->getKey(),
                    'staff_profile_id' => $staff->getKey(),
                ]);
            }

            foreach ($data['service_ids'] ?? [] as $serviceId) {
                $service = $lockedBusiness->services()->whereKey($serviceId)->firstOrFail();
                StaffServiceAssignment::query()->updateOrCreate(
                    ['business_id' => $lockedBusiness->getKey(), 'staff_profile_id' => $staff->getKey(), 'service_id' => $service->getKey()],
                    ['is_qualified' => true, 'is_active' => true, 'online_visible' => true],
                );
            }

            $this->audit->write(
                'activation.provider.saved',
                $lockedBusiness,
                target: $staff,
                before: $before,
                after: [
                    ...$staff->only(['display_name', 'email', 'mobile', 'title', 'online_visible', 'status']),
                    'location_public_id' => $location->public_id,
                    'working_days' => array_column($rules, 'day_of_week'),
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                ],
            );
            $this->onboarding->saveStep($lockedBusiness, 'staff');
            $this->onboarding->saveStep($lockedBusiness, 'staff_availability');

            return $staff;
        }, 3);

        return $staff->fresh(['locations', 'availabilityRules', 'serviceAssignments']);
    }

    /** @param array<string, mixed> $data */
    public function saveService(Business $business, array $data): Service
    {
        $service = DB::transaction(function () use ($business, $data): Service {
            $lockedBusiness = Business::query()->lockForUpdate()->findOrFail($business->getKey());
            $category = ServiceCategory::query()->firstOrCreate(
                ['business_id' => $lockedBusiness->getKey(), 'name' => trim($data['category'])],
            );
            $service = filled($data['service'] ?? null)
                ? $lockedBusiness->services()->where('public_id', $data['service'])->lockForUpdate()->firstOrFail()
                : $lockedBusiness->services()->where('kind', 'service')->whereRaw('lower(name) = ?', [strtolower(trim($data['name']))])->lockForUpdate()->first();
            $service ??= new Service(['business_id' => $lockedBusiness->getKey(), 'kind' => 'service']);
            $before = $service->exists ? $service->only(['name', 'price_minor', 'duration_minutes', 'is_active', 'online_visible']) : [];

            if (($data['deposit_type'] ?? 'none') !== 'none') {
                $this->entitlements->authorize($lockedBusiness, 'deposits.enabled', 'use');
            }

            $service->fill([
                'service_category_id' => $category->getKey(),
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'price_type' => $data['price_type'],
                'price_minor' => $data['price_minor'],
                'currency_code' => strtoupper($lockedBusiness->currency_code ?: 'INR'),
                'tax_category' => $data['tax_category'] ?? null,
                'tax_inclusive' => $lockedBusiness->tax_posture === 'inclusive',
                'duration_minutes' => $data['duration_minutes'],
                'processing_minutes' => $data['processing_minutes'] ?? 0,
                'cleanup_minutes' => $data['cleanup_minutes'] ?? 0,
                'minimum_notice_minutes' => $data['minimum_notice_minutes'] ?? 0,
                'maximum_advance_days' => $data['maximum_advance_days'] ?? 365,
                'deposit_type' => $data['deposit_type'] ?? 'none',
                'deposit_value' => $data['deposit_value'] ?? 0,
                'client_eligibility' => $data['client_eligibility'] ?? 'all',
                'consultation_required' => (bool) ($data['consultation_required'] ?? false),
                'online_visible' => (bool) ($data['online_visible'] ?? true),
                'is_active' => true,
            ])->save();

            $locationIds = array_values(array_unique($data['location_ids']));
            $staffIds = array_values(array_unique($data['staff_ids']));
            $lockedBusiness->locations()->whereIn('id', $locationIds)->get()->tap(function ($locations) use ($locationIds): void {
                abort_unless($locations->count() === count($locationIds), 404);
            });
            $lockedBusiness->staffProfiles()->whereIn('id', $staffIds)->where('status', 'active')->get()->tap(function ($staff) use ($staffIds): void {
                abort_unless($staff->count() === count($staffIds), 404);
            });
            $service->locations()->sync(collect($locationIds)->mapWithKeys(fn (int $id): array => [
                $id => ['business_id' => $lockedBusiness->getKey(), 'is_eligible' => true],
            ])->all());
            StaffServiceAssignment::query()->where('business_id', $lockedBusiness->getKey())->where('service_id', $service->getKey())->update([
                'is_active' => false,
                'online_visible' => false,
            ]);
            foreach ($staffIds as $staffId) {
                StaffServiceAssignment::query()->updateOrCreate(
                    ['business_id' => $lockedBusiness->getKey(), 'staff_profile_id' => $staffId, 'service_id' => $service->getKey()],
                    ['is_qualified' => true, 'is_active' => true, 'online_visible' => true],
                );
            }

            foreach ([
                ['kind' => 'active', 'minutes' => (int) $data['duration_minutes'], 'occupies_staff' => true],
                ['kind' => 'processing', 'minutes' => (int) ($data['processing_minutes'] ?? 0), 'occupies_staff' => false],
                ['kind' => 'cleanup', 'minutes' => (int) ($data['cleanup_minutes'] ?? 0), 'occupies_staff' => true],
            ] as $index => $segment) {
                if ($segment['minutes'] > 0) {
                    ServiceSegment::query()->updateOrCreate(
                        ['business_id' => $lockedBusiness->getKey(), 'service_id' => $service->getKey(), 'sequence' => $index + 1],
                        ['kind' => $segment['kind'], 'duration_minutes' => $segment['minutes'], 'occupies_staff' => $segment['occupies_staff']],
                    );
                }
            }

            $this->audit->write(
                'activation.service.saved',
                $lockedBusiness,
                target: $service,
                before: $before,
                after: [
                    ...$service->only(['name', 'price_minor', 'currency_code', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'is_active', 'online_visible']),
                    'location_count' => count($locationIds),
                    'provider_count' => count($staffIds),
                ],
            );
            $this->onboarding->saveStep($lockedBusiness, 'services');

            return $service;
        }, 3);

        return $service->fresh(['category', 'locations', 'staffAssignments']);
    }

    public function setServiceActive(Business $business, Service $service, bool $active): Service
    {
        abort_unless((int) $service->business_id === (int) $business->getKey(), 404);
        $before = $service->only(['is_active', 'online_visible']);
        $service->forceFill(['is_active' => $active, 'online_visible' => $active])->save();
        $this->audit->write('activation.service.status_changed', $business, target: $service, before: $before, after: $service->only(['is_active', 'online_visible']));

        return $service->fresh();
    }

    /** @return list<array{day:int,opens:string,closes:string}> */
    private function storedLocationWindows(Location $location): array
    {
        return $location->hours()->orderBy('day_of_week')->orderBy('sequence')->get()
            ->map(fn (LocationHour $hour): array => [
                'day' => (int) $hour->day_of_week,
                'opens' => substr((string) $hour->opens_at, 0, 5),
                'closes' => substr((string) $hour->closes_at, 0, 5),
            ])->values()->all();
    }

    /** @param list<int> $days @return list<array{day:int,opens:string,closes:string}> */
    private function requestedWindows(array $days, string $opensAt, string $closesAt): array
    {
        sort($days);

        return collect(array_values(array_unique($days)))->map(fn (int $day): array => [
            'day' => $day,
            'opens' => substr($opensAt, 0, 5),
            'closes' => substr($closesAt, 0, 5),
        ])->all();
    }

    /** @return list<array{day:int,location:int,opens:string,closes:string}> */
    private function storedStaffWindows(StaffProfile $staff): array
    {
        return $staff->availabilityRules()->where('kind', 'working')->orderBy('day_of_week')->orderBy('sequence')->get()
            ->map(fn (StaffAvailabilityRule $rule): array => [
                'day' => (int) $rule->day_of_week,
                'location' => (int) $rule->location_id,
                'opens' => substr((string) $rule->starts_at, 0, 5),
                'closes' => substr((string) $rule->ends_at, 0, 5),
            ])->values()->all();
    }

    /** @param list<array<string, mixed>> $rules @return list<array{day:int,location:int,opens:string,closes:string}> */
    private function requestedStaffWindows(array $rules): array
    {
        return collect($rules)->sortBy(['day_of_week', 'sequence'])->map(fn (array $rule): array => [
            'day' => (int) $rule['day_of_week'],
            'location' => (int) $rule['location_id'],
            'opens' => substr((string) $rule['starts_at'], 0, 5),
            'closes' => substr((string) $rule['ends_at'], 0, 5),
        ])->values()->all();
    }
}
