<?php

namespace App\Domain\SchedulingOperations\Data;

use Carbon\CarbonImmutable;

final readonly class AvailabilitySearch
{
    /** @param list<BookingLineRequest> $lines */
    public function __construct(
        public int $businessId,
        public int $locationId,
        public CarbonImmutable $fromLocalDate,
        public CarbonImmutable $untilLocalDate,
        public array $lines,
        public string $source = 'online',
        public string $clientEligibility = 'existing',
        public int $limit = 50,
        public ?CarbonImmutable $asOfUtc = null,
    ) {}
}
