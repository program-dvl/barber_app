<?php

namespace App\Domain\BusinessConfiguration\Contracts;

use App\Domain\BusinessConfiguration\Data\CapacityRequirement;
use App\Domain\BusinessConfiguration\Data\EffectiveService;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Carbon\CarbonImmutable;

interface AvailabilityConfiguration
{
    /** @return list<array{opens_at:string,closes_at:string,source:string}> */
    public function locationWindows(Location $location, CarbonImmutable $localDate): array;

    /** @return list<array{opens_at:string,closes_at:string,source:string}> */
    public function staffWindows(StaffProfile $staff, Location $location, CarbonImmutable $localDate): array;

    /** @return list<array{opens_at:string,closes_at:string,source:string}> */
    public function resourceWindows(PhysicalResource $resource, CarbonImmutable $localDate): array;

    /** @return list<array{starts_at_utc:string,ends_at_utc:string,reason:string}> */
    public function resourceMaintenance(PhysicalResource $resource, CarbonImmutable $fromUtc, CarbonImmutable $untilUtc): array;

    public function resolveService(Service $service, StaffProfile $staff, Location $location, ?CarbonImmutable $at = null): EffectiveService;

    /** @param list<Service> $addons @return list<CapacityRequirement> */
    public function requiredCapacity(Service $service, array $addons = []): array;
}
