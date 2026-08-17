<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClientAttachment extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'client_id', 'appointment_id', 'client_note_id', 'uploaded_by_staff_profile_id', 'kind', 'visibility', 'disk', 'object_key', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'scan_status', 'retention_class', 'retention_until'];

    protected static function booted(): void
    {
        static::creating(fn (ClientAttachment $attachment) => $attachment->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'retention_until' => 'immutable_datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'uploaded_by_staff_profile_id');
    }

    public function accessLinks(): HasMany
    {
        return $this->hasMany(ClientAttachmentAccessLink::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
