<?php

namespace App\Domain\Communications\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CommunicationSuppression extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'client_id', 'channel', 'recipient_hash', 'scope', 'reason', 'source', 'suppressed_at', 'released_at'];

    protected function casts(): array
    {
        return ['suppressed_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }
}
