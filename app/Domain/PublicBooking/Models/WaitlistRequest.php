<?php

namespace App\Domain\PublicBooking\Models;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WaitlistRequest extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'location_id', 'service_id', 'preferred_staff_profile_id', 'origin_appointment_id', 'public_id', 'client_name', 'client_mobile', 'client_email', 'acceptable_from', 'acceptable_until', 'time_from', 'time_until', 'notification_method', 'notes', 'status', 'active_dedupe_key', 'expires_at', 'version'];

    protected static function booted(): void
    {
        static::creating(fn (WaitlistRequest $request) => $request->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['acceptable_from' => 'immutable_date', 'acceptable_until' => 'immutable_date', 'expires_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'preferred_staff_profile_id');
    }

    public function originAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'origin_appointment_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(WaitlistMatch::class);
    }
}
