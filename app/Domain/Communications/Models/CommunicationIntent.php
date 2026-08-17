<?php

namespace App\Domain\Communications\Models;

use App\Domain\ClientRecords\Models\Client;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationIntent extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'client_id', 'source_type', 'source_id', 'event_type', 'event_key', 'intent_type', 'category', 'legal_basis', 'locale', 'time_zone', 'scheduled_for_utc', 'local_scheduled_for', 'status', 'correlation_id'];

    protected function casts(): array
    {
        return ['scheduled_for_utc' => 'immutable_datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
