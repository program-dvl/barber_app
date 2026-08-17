<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentServiceLine extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'appointment_id', 'service_id', 'primary_staff_profile_id', 'sequence',
        'name', 'price_minor', 'currency_code', 'bookable_minutes', 'configuration_snapshot',
    ];

    protected function casts(): array
    {
        return ['configuration_snapshot' => 'array', 'price_minor' => 'integer', 'bookable_minutes' => 'integer'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function primaryStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'primary_staff_profile_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(AppointmentSegment::class)->orderBy('sequence');
    }
}
