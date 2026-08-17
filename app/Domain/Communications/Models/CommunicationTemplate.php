<?php

namespace App\Domain\Communications\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommunicationTemplate extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'intent_type', 'channel', 'locale', 'version', 'status', 'subject', 'body', 'variables', 'fallbacks', 'provider_template_id', 'provider_template_status', 'published_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $template) => $template->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['variables' => 'array', 'fallbacks' => 'array', 'version' => 'integer', 'published_at' => 'immutable_datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
