<?php

namespace App\Filament\Resources\Concerns;

trait DisablesUnapprovedPlatformResource
{
    public static function canAccess(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
