<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Contracts\AvailabilityConfiguration;
use App\Domain\BusinessConfiguration\Data\EffectiveService;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Carbon\CarbonImmutable;

class AvailabilityConfigurationService implements AvailabilityConfiguration
{
    public function __construct(
        private readonly LocalHoursResolver $hours,
        private readonly EffectiveServiceResolver $services,
        private readonly CapacityRequirementResolver $capacity,
        private readonly StaffAvailabilityResolver $staff,
        private readonly ResourceAvailabilityResolver $resources,
    ) {}

    public function locationWindows(Location $location, CarbonImmutable $localDate): array
    {
        return $this->hours->windows($location, $localDate);
    }

    public function resolveService(Service $service, StaffProfile $staff, Location $location, ?CarbonImmutable $at = null): EffectiveService
    {
        return $this->services->resolve($service, $staff, $location, $at);
    }

    public function staffWindows(StaffProfile $staff, Location $location, CarbonImmutable $localDate): array
    {
        return $this->staff->windows($staff, $location, $localDate);
    }

    public function resourceWindows(PhysicalResource $resource, CarbonImmutable $localDate): array
    {
        return $this->resources->windows($resource, $localDate);
    }

    public function resourceMaintenance(PhysicalResource $resource, CarbonImmutable $fromUtc, CarbonImmutable $untilUtc): array
    {
        return $this->resources->maintenance($resource, $fromUtc, $untilUtc);
    }

    public function requiredCapacity(Service $service, array $addons = []): array
    {
        return $this->capacity->resolve($service, $addons);
    }
}
