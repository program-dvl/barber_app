<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BillingProviderEvent extends Model
{
    protected $fillable = ['public_id', 'business_id', 'provider', 'provider_event_id', 'event_type', 'status', 'signature_verified', 'provider_created_at', 'processed_at', 'attempts', 'payload_hash', 'payload', 'last_error'];

    protected static function booted(): void
    {
        static::creating(fn (BillingProviderEvent $event) => $event->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['signature_verified' => 'boolean', 'provider_created_at' => 'datetime', 'processed_at' => 'datetime', 'payload' => 'array'];
    }
}
