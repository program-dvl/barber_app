<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentSegment extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'appointment_id', 'appointment_service_line_id', 'service_segment_id',
        'staff_profile_id', 'kind', 'sequence', 'starts_at_utc', 'ends_at_utc', 'time_zone',
        'local_starts_at', 'local_ends_at', 'occupies_staff',
    ];

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime',
            'occupies_staff' => 'boolean',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function serviceLine(): BelongsTo
    {
        return $this->belongsTo(AppointmentServiceLine::class, 'appointment_service_line_id');
    }

    public function serviceSegment(): BelongsTo
    {
        return $this->belongsTo(ServiceSegment::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }
}
