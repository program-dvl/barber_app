<?php

namespace App\Domain\Communications\Data;

use Carbon\CarbonImmutable;

final readonly class CommunicationIntentData
{
    /** @param array<string,string> $recipients @param array<string,scalar|null> $variables */
    public function __construct(
        public int $businessId,
        public string $eventKey,
        public string $eventType,
        public string $intentType,
        public string $category,
        public string $legalBasis,
        public string $locale,
        public string $timeZone,
        public CarbonImmutable $scheduledForUtc,
        public array $recipients,
        public array $variables,
        public ?int $clientId = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $correlationId = null,
        public ?string $actionPurpose = null,
    ) {}
}
