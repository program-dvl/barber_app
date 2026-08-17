<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CommerceSetting extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'currency_code', 'tax_inclusive', 'default_tax_rate_bps', 'default_deposit_type', 'default_deposit_value', 'deposit_new_clients_only', 'deposit_threshold_minor', 'deposit_prior_no_show_count', 'cancellation_cutoff_minutes', 'deposit_refundable_before_cutoff', 'cancellation_fee_minor', 'no_show_fee_minor', 'discount_manager_limit_bps'];

    protected function casts(): array
    {
        return ['tax_inclusive' => 'boolean', 'default_tax_rate_bps' => 'integer', 'default_deposit_value' => 'integer', 'deposit_new_clients_only' => 'boolean', 'deposit_threshold_minor' => 'integer', 'deposit_prior_no_show_count' => 'integer', 'cancellation_cutoff_minutes' => 'integer', 'deposit_refundable_before_cutoff' => 'boolean', 'cancellation_fee_minor' => 'integer', 'no_show_fee_minor' => 'integer', 'discount_manager_limit_bps' => 'integer'];
    }
}
