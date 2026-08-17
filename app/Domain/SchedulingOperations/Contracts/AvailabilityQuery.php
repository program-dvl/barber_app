<?php

namespace App\Domain\SchedulingOperations\Contracts;

use App\Domain\SchedulingOperations\Data\AvailabilitySearch;

interface AvailabilityQuery
{
    /** @return list<array<string, mixed>> */
    public function search(AvailabilitySearch $query): array;
}
