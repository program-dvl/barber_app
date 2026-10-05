<?php

namespace App\Domain\AccountNotifications\Models;

use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailNotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id', 'business_id', 'user_id', 'notification_type', 'stream',
        'mailer', 'recipient_hash', 'recipient_masked', 'status', 'provider_message_id',
        'attempts', 'sending_at', 'sent_at', 'failed_at', 'last_error_code', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sending_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
