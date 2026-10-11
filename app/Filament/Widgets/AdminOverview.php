<?php

namespace App\Filament\Widgets;

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return UserResource::canAccess();
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Users', User::query()->count()),
            Stat::make('Businesses', Business::query()->count()),
            Stat::make('Active business subscriptions', BusinessSubscription::query()->where('status', SubscriptionStatus::Active)->count()),
        ];
    }
}
