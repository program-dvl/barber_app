<?php

namespace App\Domain\ClientReviews\Models;

use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReview extends Model
{
    use BelongsToBusiness;
    use HasUlids;

    protected $fillable = ['business_id', 'appointment_id', 'client_id', 'rating', 'body', 'author_initials', 'public_consent', 'public_consent_evidence', 'request_hash', 'submitted_at', 'public_consent_withdrawn_at'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return ['rating' => 'integer', 'public_consent' => 'boolean', 'public_consent_evidence' => 'array', 'submitted_at' => 'immutable_datetime', 'public_consent_withdrawn_at' => 'immutable_datetime'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
