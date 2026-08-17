<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CashClose extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'location_id', 'business_date', 'currency_code', 'opening_cash_minor', 'expected_cash_minor', 'actual_cash_minor', 'variance_minor', 'variance_reason', 'method_summary', 'outstanding_balance_minor', 'closed_by_membership_id', 'closed_at'];

    protected function casts(): array
    {
        return ['business_date' => 'immutable_date', 'opening_cash_minor' => 'integer', 'expected_cash_minor' => 'integer', 'actual_cash_minor' => 'integer', 'variance_minor' => 'integer', 'method_summary' => 'array', 'closed_at' => 'immutable_datetime'];
    }
}
