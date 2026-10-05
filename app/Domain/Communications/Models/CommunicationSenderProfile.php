<?php

namespace App\Domain\Communications\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommunicationSenderProfile extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'public_id', 'channel', 'mode', 'provider', 'status', 'country_code',
        'display_name', 'sender_identifier', 'sender_identifier_hash', 'provider_account_sid', 'provider_account_sid_hash', 'provider_api_key_sid',
        'provider_api_key_secret', 'webhook_auth_token', 'capabilities', 'metadata', 'verified_at',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $profile) => $profile->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'sender_identifier' => 'encrypted',
            'provider_account_sid' => 'encrypted',
            'provider_api_key_sid' => 'encrypted',
            'provider_api_key_secret' => 'encrypted',
            'webhook_auth_token' => 'encrypted',
            'capabilities' => 'array',
            'metadata' => 'encrypted:array',
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function maskedIdentifier(): ?string
    {
        if (! $this->sender_identifier) {
            return null;
        }

        $value = preg_replace('/^whatsapp:/', '', $this->sender_identifier);

        return str_starts_with($value, '+') && strlen($value) > 6
            ? substr($value, 0, 3).str_repeat('•', max(4, strlen($value) - 7)).substr($value, -4)
            : $value;
    }
}
