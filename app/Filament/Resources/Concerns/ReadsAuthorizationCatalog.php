<?php

namespace App\Filament\Resources\Concerns;

trait ReadsAuthorizationCatalog
{
    use ReadsAdminRecords;

    public static function getRelations(): array
    {
        return [];
    }
}
