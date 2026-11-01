<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingPromotion;
use Illuminate\Database\Seeder;

class LaunchPromotionSeeder extends Seeder
{
    public function run(): void
    {
        // Repeat deployment never re-enables or overwrites an operator's changes.
        BillingPromotion::firstOrCreate(['public_id' => '01K8Z9WY000000000000000020'], [
            'name' => 'Holiday welcome',
            'percentage' => 20, 'is_active' => true, 'can_combine' => false,
            'starts_at' => '2026-11-01 00:00:00', 'ends_at' => '2027-01-01 00:00:00',
        ]);
    }
}
