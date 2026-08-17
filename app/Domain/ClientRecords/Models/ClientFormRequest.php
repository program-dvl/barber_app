<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ClientFormRequest extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'client_id', 'appointment_id', 'client_form_template_version_id', 'status', 'requested_at', 'due_at', 'completed_at'];

    protected static function booted(): void
    {
        static::creating(fn (ClientFormRequest $request) => $request->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['requested_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ClientFormTemplateVersion::class, 'client_form_template_version_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function submission(): HasOne
    {
        return $this->hasOne(ClientFormSubmission::class);
    }
}
