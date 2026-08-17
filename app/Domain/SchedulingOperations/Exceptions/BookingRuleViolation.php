<?php

namespace App\Domain\SchedulingOperations\Exceptions;

use DomainException;

class BookingRuleViolation extends DomainException
{
    /** @param array<string, scalar|null> $safeContext */
    public function __construct(
        public readonly string $ruleCode,
        string $safeMessage,
        public readonly array $safeContext = [],
    ) {
        parent::__construct($safeMessage);
    }

    /** @return array{code:string,message:string,context:array<string, scalar|null>} */
    public function toDomainError(): array
    {
        return ['code' => $this->ruleCode, 'message' => $this->getMessage(), 'context' => $this->safeContext];
    }
}
