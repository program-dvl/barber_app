<?php

namespace App\Support\Tenancy;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::creating(function ($model): void {
            if (! $model->business_id) {
                throw new LogicException(class_basename($model).' requires an explicit business_id.');
            }
        });

        static::saving(function ($model): void {
            $context = app(TenantContext::class);

            if ($context->hasBusiness() && (int) $model->business_id !== (int) $context->business()->getKey()) {
                throw new LogicException('A tenant-owned model cannot be written from another tenant context.');
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeForBusiness(Builder $query, Business|int $business): Builder
    {
        return $query->where($query->qualifyColumn('business_id'), $business instanceof Business ? $business->getKey() : $business);
    }
}
