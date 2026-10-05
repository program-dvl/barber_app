<?php

namespace App\Domain\Communications\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationInboundMessage extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'communication_conversation_id', 'provider', 'provider_message_id',
        'channel', 'sender_hash', 'sender', 'body', 'media', 'received_at',
    ];

    protected function casts(): array
    {
        return ['sender' => 'encrypted', 'body' => 'encrypted', 'media' => 'encrypted:array', 'received_at' => 'immutable_datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CommunicationConversation::class, 'communication_conversation_id');
    }
}
