<?php

namespace App\Domain\PublicBooking\Models;

use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicAppointmentLink extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'appointment_id', 'purpose', 'token_hash', 'expires_at', 'used_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
