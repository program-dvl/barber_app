<?php

namespace App\Filament\Resources\BillingPromotions\Pages;

use App\Filament\Resources\BillingPromotions\BillingPromotionResource;
use Filament\Resources\Pages\EditRecord;

class EditBillingPromotion extends EditRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = BillingPromotionResource::class;
}
