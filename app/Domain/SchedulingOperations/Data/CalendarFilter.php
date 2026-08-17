<?php

namespace App\Domain\SchedulingOperations\Data;

use Carbon\CarbonImmutable;

final readonly class CalendarFilter
{
    /** @param list<int> $staffIds @param list<int> $serviceIds @param list<string> $statuses */
    public function __construct(
        public int $businessId,
        public int $locationId,
        public string $view,
        public CarbonImmutable $localDate,
        public array $staffIds = [],
        public array $serviceIds = [],
        public array $statuses = [],
    ) {}
}
