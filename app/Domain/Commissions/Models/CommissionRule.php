<?php

namespace App\Domain\Commissions\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class CommissionRule extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'staff_profile_id', 'service_id', 'public_id', 'kind', 'rate_bps', 'amount_minor', 'currency_code', 'effective_from', 'effective_to', 'created_by_membership_id', 'reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $rule) => $rule->public_id ??= (string) Str::ulid());
        static::updating(fn () => throw new LogicException('Commission rules are versioned, not edited.'));
        static::deleting(fn () => throw new LogicException('Commission rules preserve payroll history.'));
    }

    protected function casts(): array
    {
        return ['rate_bps' => 'integer', 'amount_minor' => 'integer', 'effective_from' => 'immutable_datetime', 'effective_to' => 'immutable_datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
