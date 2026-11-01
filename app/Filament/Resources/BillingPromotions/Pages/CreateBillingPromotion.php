<?php

namespace App\Filament\Resources\BillingPromotions\Pages;

use App\Filament\Resources\BillingPromotions\BillingPromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBillingPromotion extends CreateRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = BillingPromotionResource::class;
}
