<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientNote extends Model
{
    use BelongsToBusiness;

    public const KINDS = ['general', 'allergy', 'sensitivity', 'formula', 'hair', 'skin', 'treatment', 'patch_test', 'preference', 'warning'];

    protected $fillable = ['business_id', 'client_id', 'appointment_id', 'authored_by_staff_profile_id', 'kind', 'visibility', 'content', 'is_important'];

    protected function casts(): array
    {
        return ['content' => 'encrypted', 'is_important' => 'boolean'];
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
