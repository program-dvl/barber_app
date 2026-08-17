<?php

namespace App\Domain\SchedulingOperations\Data;

final readonly class BookingLineRequest
{
    /**
     * @param  array<int, int>  $segmentStaffIds  Segment sequence to StaffProfile ID.
     */
    public function __construct(
        public int $serviceId,
        public ?int $preferredStaffId = null,
        public array $segmentStaffIds = [],
        public bool $allowAnyQualified = true,
        public ?int $durationOverrideMinutes = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $segmentStaffIds = $this->segmentStaffIds;
        ksort($segmentStaffIds);

        return [
            'service_id' => $this->serviceId,
            'preferred_staff_id' => $this->preferredStaffId,
            'segment_staff_ids' => $segmentStaffIds,
            'allow_any_qualified' => $this->allowAnyQualified,
            'duration_override_minutes' => $this->durationOverrideMinutes,
        ];
    }
}
