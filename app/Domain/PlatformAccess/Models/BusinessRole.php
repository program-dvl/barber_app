<?php

namespace App\Domain\PlatformAccess\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class BusinessRole extends Role
{
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
