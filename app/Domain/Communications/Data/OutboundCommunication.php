<?php

namespace App\Domain\Communications\Data;

final readonly class OutboundCommunication
{
    /** @param array<string, scalar|null> $providerVariables */
    public function __construct(
        public string $destination,
        public string $subject,
        public string $body,
        public string $idempotencyKey,
        public string $correlationId,
        public ?string $providerTemplateId = null,
        public array $providerVariables = [],
    ) {}
}
