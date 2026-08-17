<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BillingCoupon extends Model
{
    protected $fillable = ['public_id', 'code', 'provider', 'provider_coupon_id', 'discount_type', 'discount_value', 'duration_months', 'maximum_redemptions', 'redemptions_count', 'valid_from', 'valid_until', 'is_active'];

    protected static function booted(): void
    {
        static::creating(function (BillingCoupon $coupon): void {
            $coupon->public_id ??= (string) Str::ulid();
            $coupon->code = Str::upper($coupon->code);
        });
    }

    protected function casts(): array
    {
        return ['valid_from' => 'immutable_datetime', 'valid_until' => 'immutable_datetime', 'is_active' => 'boolean'];
    }

    public function isRedeemable(): bool
    {
        return $this->is_active
            && $this->valid_from->isPast()
            && (! $this->valid_until || $this->valid_until->isFuture())
            && (! $this->maximum_redemptions || $this->redemptions_count < $this->maximum_redemptions);
    }
}
