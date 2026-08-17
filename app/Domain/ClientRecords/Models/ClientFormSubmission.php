<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\ClientRecords\Support\ImmutableRecord;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientFormSubmission extends Model
{
    use BelongsToBusiness;
    use ImmutableRecord;

    protected $fillable = ['business_id', 'public_id', 'client_id', 'appointment_id', 'client_form_request_id', 'client_form_template_version_id', 'wording_snapshot', 'answers', 'signature', 'signature_hash', 'submitted_identity_snapshot', 'submitted_at'];

    protected static function booted(): void
    {
        static::creating(fn (ClientFormSubmission $submission) => $submission->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['wording_snapshot' => 'array', 'answers' => 'encrypted:array', 'signature' => 'encrypted', 'submitted_identity_snapshot' => 'encrypted:array', 'submitted_at' => 'immutable_datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ClientFormRequest::class, 'client_form_request_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ClientFormTemplateVersion::class, 'client_form_template_version_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
