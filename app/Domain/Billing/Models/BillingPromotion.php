<?php

namespace App\Domain\Billing\Models;

use App\Support\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingPromotion extends Model
{
    protected $fillable = ['public_id', 'name', 'code', 'percentage', 'is_active', 'can_combine', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['percentage' => 'integer', 'is_active' => 'boolean', 'can_combine' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $promotion): void {
            $promotion->public_id ??= (string) Str::ulid();
            $promotion->code = filled($promotion->code) ? Str::upper(trim($promotion->code)) : null;
            if ($promotion->percentage < 1 || $promotion->percentage > 90) {
                throw ValidationException::withMessages(['percentage' => 'Choose a percentage from 1 to 90.']);
            }
            if ($promotion->code && ! preg_match('/^[A-Z0-9][A-Z0-9-]{2,39}$/D', $promotion->code)) {
                throw ValidationException::withMessages(['code' => 'Use 3–40 letters, numbers or hyphens.']);
            }
            if ($promotion->starts_at && $promotion->ends_at && $promotion->ends_at <= $promotion->starts_at) {
                throw ValidationException::withMessages(['ends_at' => 'Choose an end after the start.']);
            }
        });
        static::deleting(fn () => throw new \LogicException('Offers are retained as billing history. Disable the offer instead.'));
        static::saved(function (self $promotion): void {
            app(AuditWriter::class)->write('billing.promotion.saved', target: $promotion,
                before: $promotion->getRawOriginal(), after: $promotion->getAttributes());
        });
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
