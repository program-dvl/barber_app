<?php

namespace App\Domain\Communications\Data;

final readonly class ProviderSendResult
{
    public function __construct(
        public string $providerMessageId,
        public ?string $providerRequestId = null,
        public string $acceptedState = 'sent',
    ) {}
}
