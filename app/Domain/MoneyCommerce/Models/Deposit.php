<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'appointment_id', 'client_id', 'payment_transaction_id', 'original_amount_minor', 'applied_minor', 'refunded_minor', 'forfeited_minor', 'credited_minor', 'currency_code', 'status', 'policy_snapshot'];

    protected function casts(): array
    {
        return ['original_amount_minor' => 'integer', 'applied_minor' => 'integer', 'refunded_minor' => 'integer', 'forfeited_minor' => 'integer', 'credited_minor' => 'integer', 'policy_snapshot' => 'array'];
    }

    public function remainingMinor(): int
    {
        return $this->original_amount_minor - $this->applied_minor - $this->refunded_minor - $this->forfeited_minor - $this->credited_minor;
    }
}
