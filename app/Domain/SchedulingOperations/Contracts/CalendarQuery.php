<?php

namespace App\Domain\SchedulingOperations\Contracts;

use App\Domain\SchedulingOperations\Data\CalendarFilter;

interface CalendarQuery
{
    /** @return array<string, mixed> */
    public function calendar(CalendarFilter $filter): array;
}
