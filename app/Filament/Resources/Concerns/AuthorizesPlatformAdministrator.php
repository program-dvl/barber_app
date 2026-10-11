<?php

namespace App\Filament\Resources\Concerns;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesPlatformAdministrator
{
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user?->hasVerifiedEmail() && $user->hasPlatformRole(PlatformRole::Administrator);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }
}
