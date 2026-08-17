<?php

namespace App\Domain\Communications\Exceptions;

use RuntimeException;

class CommunicationProviderException extends RuntimeException
{
    public function __construct(public readonly string $safeCode, public readonly bool $retryable)
    {
        parent::__construct('Communication provider request failed: '.$safeCode);
    }
}
