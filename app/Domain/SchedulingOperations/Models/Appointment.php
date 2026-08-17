<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientFormRequest;
use App\Domain\PlatformAccess\Models\Location;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'location_id', 'client_id', 'rescheduled_from_appointment_id', 'rescheduled_to_appointment_id',
        'public_id', 'booking_reference', 'idempotency_key', 'request_hash', 'status', 'source', 'client_name',
        'client_mobile', 'client_email', 'client_date_of_birth', 'referral_source', 'communication_preferences',
        'marketing_opt_in', 'special_request', 'public_policy_snapshot', 'internal_notes', 'starts_at_utc', 'ends_at_utc', 'time_zone',
        'local_starts_at', 'local_ends_at', 'price_minor', 'currency_code', 'confirmed_at',
        'version', 'arrived_at', 'checked_in_at', 'service_started_at', 'completed_at', 'cancelled_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            $appointment->public_id ??= (string) Str::ulid();
            $appointment->booking_reference ??= 'GH-'.substr($appointment->public_id, -10);
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime', 'arrived_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime', 'service_started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
            'client_date_of_birth' => 'immutable_date', 'communication_preferences' => 'array',
            'marketing_opt_in' => 'boolean', 'public_policy_snapshot' => 'array',
            'price_minor' => 'integer', 'version' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceLines(): HasMany
    {
        return $this->hasMany(AppointmentServiceLine::class)->orderBy('sequence');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(AppointmentSegment::class)->orderBy('starts_at_utc');
    }

    public function resourceClaims(): HasMany
    {
        return $this->hasMany(AppointmentResourceClaim::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->orderBy('occurred_at');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(AppointmentChange::class)->orderBy('occurred_at');
    }

    public function formRequests(): HasMany
    {
        return $this->hasMany(ClientFormRequest::class)->orderByDesc('requested_at');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
