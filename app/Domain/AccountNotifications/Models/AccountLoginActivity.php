<?php

namespace App\Domain\AccountNotifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountLoginActivity extends Model
{
    protected $fillable = [
        'user_id', 'fingerprint', 'device_label', 'ip_address', 'user_agent',
        'first_seen_at', 'last_seen_at', 'last_notified_at', 'sign_in_count',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'last_notified_at' => 'immutable_datetime',
            'sign_in_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
