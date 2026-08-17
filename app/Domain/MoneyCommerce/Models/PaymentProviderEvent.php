<?php

namespace App\Domain\MoneyCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProviderEvent extends Model
{
    protected $fillable = ['business_id', 'provider', 'provider_event_id', 'payload_hash', 'signature_verified', 'provider_created_at', 'event_type', 'processing_status', 'attempts', 'payload', 'error', 'processed_at'];

    protected function casts(): array
    {
        return ['signature_verified' => 'boolean', 'provider_created_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime', 'payload' => 'array'];
    }
}
