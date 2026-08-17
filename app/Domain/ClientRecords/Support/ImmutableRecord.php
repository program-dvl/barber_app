<?php

namespace App\Domain\ClientRecords\Support;

use LogicException;

trait ImmutableRecord
{
    public static function bootImmutableRecord(): void
    {
        static::updating(fn () => throw new LogicException(class_basename(static::class).' records are immutable.'));
        static::deleting(fn () => throw new LogicException(class_basename(static::class).' records are immutable.'));
    }
}
