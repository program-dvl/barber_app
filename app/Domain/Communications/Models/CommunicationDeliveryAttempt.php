<?php

namespace App\Domain\Communications\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CommunicationDeliveryAttempt extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'communication_message_id', 'attempt_number', 'idempotency_key', 'status', 'provider', 'provider_request_id', 'provider_message_id', 'error_code', 'error_class', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['attempt_number' => 'integer', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }
}
