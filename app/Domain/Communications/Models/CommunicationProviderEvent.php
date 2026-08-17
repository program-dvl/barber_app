<?php

namespace App\Domain\Communications\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationProviderEvent extends Model
{
    protected $fillable = ['provider', 'provider_event_id', 'business_id', 'communication_message_id', 'provider_message_id', 'event_type', 'payload_hash', 'signature_verified', 'status', 'attempts', 'provider_occurred_at', 'processed_at', 'last_error_code'];

    protected function casts(): array
    {
        return ['signature_verified' => 'boolean', 'attempts' => 'integer', 'provider_occurred_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
