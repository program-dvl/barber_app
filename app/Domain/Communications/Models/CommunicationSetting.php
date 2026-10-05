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
    ];

    protected function casts(): array
    {
        return [
            'reminder_offsets_minutes' => 'array', 'marketing_enabled' => 'boolean',
            'smart_fallback_enabled' => 'boolean', 'two_way_enabled' => 'boolean',
        ];
    }
}
