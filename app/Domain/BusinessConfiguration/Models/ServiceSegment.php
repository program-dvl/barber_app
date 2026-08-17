<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceSegment extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'service_id', 'kind', 'sequence', 'duration_minutes', 'occupies_staff'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'occupies_staff' => 'boolean'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function resourceRequirements(): HasMany
    {
        return $this->hasMany(ServiceResourceRequirement::class);
    }
}
