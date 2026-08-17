<?php

namespace App\Domain\Communications\Models;

use App\Domain\ClientRecords\Models\Client;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationMessage extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'communication_intent_id', 'client_id', 'communication_template_id', 'communication_action_link_id', 'channel', 'recipient_hash', 'recipient', 'idempotency_key', 'category', 'legal_basis', 'locale', 'time_zone', 'template_variables', 'subject_hash', 'body_hash', 'status', 'attempt_count', 'max_attempts', 'provider', 'provider_message_id', 'provider_state_at', 'last_error_code', 'last_error_class', 'suppression_reason', 'queued_at', 'sent_at', 'delivered_at', 'failed_at', 'next_attempt_at'];

    protected function casts(): array
    {
        return [
            'recipient' => 'encrypted', 'template_variables' => 'encrypted:array',
            'attempt_count' => 'integer', 'max_attempts' => 'integer', 'queued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime', 'provider_state_at' => 'immutable_datetime',
        ];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(CommunicationIntent::class, 'communication_intent_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }

    public function actionLink(): BelongsTo
    {
        return $this->belongsTo(CommunicationActionLink::class, 'communication_action_link_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CommunicationDeliveryAttempt::class);
    }
}
