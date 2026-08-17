<?php

namespace App\Domain\PublicBooking\Models;

use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WaitlistMatch extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'waitlist_request_id', 'appointment_id', 'staff_profile_id', 'public_id', 'batch_id', 'claim_token_hash', 'status', 'slot_starts_at_utc', 'slot_ends_at_utc', 'offered_at', 'expires_at', 'claimed_at'];

    protected static function booted(): void
    {
        static::creating(fn (WaitlistMatch $match) => $match->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['slot_starts_at_utc' => 'immutable_datetime', 'slot_ends_at_utc' => 'immutable_datetime', 'offered_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'claimed_at' => 'immutable_datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(WaitlistRequest::class, 'waitlist_request_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }
}
