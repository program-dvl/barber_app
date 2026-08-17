<?php

namespace App\Domain\BusinessConfiguration\Data;

final readonly class EffectiveService
{
    /** @param list<array<string, mixed>> $segments */
    public function __construct(
        public int $serviceId,
        public int $staffProfileId,
        public int $locationId,
        public string $name,
        public string $priceType,
        public int $priceMinor,
        public string $currencyCode,
        public int $durationMinutes,
        public int $processingMinutes,
        public int $cleanupMinutes,
        public int $bookableMinutes,
        public string $taxCategory,
        public bool $taxInclusive,
        public string $depositType,
        public int $depositValue,
        public int $depositMinor,
        public bool $onlineVisible,
        public string $clientEligibility,
        public bool $consultationRequired,
        public int $minimumNoticeMinutes,
        public int $maximumAdvanceDays,
        public ?string $commissionRate,
        public array $segments,
        public string $resolvedAt,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return get_object_vars($this);
    }
}
