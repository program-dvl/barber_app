<?php

namespace App\Filament\Resources\BillingPromotions\Pages;

use App\Filament\Resources\BillingPromotions\BillingPromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBillingPromotions extends ListRecords
{
    protected static string $resource = BillingPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create offer')];
    }
}
