<?php

namespace App\Domain\Communications\Models;

use App\Domain\ClientRecords\Models\Client;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommunicationConversation extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'client_id', 'communication_sender_profile_id', 'public_id', 'channel',
        'participant_hash', 'participant', 'status', 'last_message_at', 'assigned_at',
        'assigned_staff_profile_id',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $conversation) => $conversation->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['participant' => 'encrypted', 'last_message_at' => 'immutable_datetime', 'assigned_at' => 'immutable_datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function inboundMessages(): HasMany
    {
        return $this->hasMany(CommunicationInboundMessage::class)->orderBy('received_at');
    }

    public function senderProfile(): BelongsTo
    {
        return $this->belongsTo(CommunicationSenderProfile::class, 'communication_sender_profile_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
