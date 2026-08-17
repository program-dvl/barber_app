<?php

namespace App\Filament\Resources\Concerns;

trait DisablesLegacyBillingResource
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
