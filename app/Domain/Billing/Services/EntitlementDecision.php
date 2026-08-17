<?php

namespace App\Domain\Billing\Services;

final readonly class EntitlementDecision
{
    public function __construct(
        public bool $allowed,
        public string $code,
        public mixed $entitledValue = null,
        public ?int $currentUsage = null,
    ) {}
}
