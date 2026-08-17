<?php

namespace App\Domain\PublicBooking\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class PublicBookingEvent extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_booking_flow_id', 'appointment_id', 'event_name', 'session_hash', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
