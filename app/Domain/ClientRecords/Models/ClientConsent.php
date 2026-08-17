<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\ClientRecords\Support\ImmutableRecord;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConsent extends Model
{
    use BelongsToBusiness;
    use ImmutableRecord;

    protected $fillable = ['business_id', 'client_id', 'appointment_id', 'authored_by_staff_profile_id', 'type', 'status', 'source', 'policy_version', 'wording', 'evidence', 'occurred_at'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'authored_by_staff_profile_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
