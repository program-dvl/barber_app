<?php

namespace App\Domain\Billing\Models;

use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerRegistrationIntent extends Model
{
    protected $fillable = ['user_id', 'business_id', 'business_name', 'selected_plan_code', 'selected_billing_interval', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
