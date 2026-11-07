<?php

namespace App\Domain\Communications\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CommunicationSetting extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'email_provider', 'mobile_channel', 'fallback_mobile_channel', 'mobile_provider',
        'sender_mode', 'smart_fallback_enabled', 'two_way_enabled', 'default_locale',
        'reminder_offsets_minutes', 'quiet_hours_start', 'quiet_hours_end', 'marketing_enabled',
        'email_enabled', 'sms_enabled', 'automatic_receipt_email', 'automatic_review_email', 'review_email_enabled_at', 'receipt_email_enabled_at',
    ];

    protected function casts(): array
    {
        return [
            'reminder_offsets_minutes' => 'array', 'marketing_enabled' => 'boolean',
            'smart_fallback_enabled' => 'boolean', 'two_way_enabled' => 'boolean',
            'automatic_receipt_email' => 'boolean', 'automatic_review_email' => 'boolean', 'review_email_enabled_at' => 'immutable_datetime', 'receipt_email_enabled_at' => 'immutable_datetime', 'email_enabled' => 'boolean', 'sms_enabled' => 'boolean',
        ];
    }
}
