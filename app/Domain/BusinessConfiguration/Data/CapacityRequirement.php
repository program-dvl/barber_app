<?php

namespace App\Domain\BusinessConfiguration\Data;

final readonly class CapacityRequirement
{
    public function __construct(
        public int $resourceId,
        public string $resourcePublicId,
        public string $resourceName,
        public ?int $segmentId,
        public int $quantity,
        public int $availableQuantity,
        public bool $satisfiable,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
