<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BusinessPhoto extends Model
{
    use BelongsToBusiness, SoftDeletes;

    protected $fillable = ['business_id', 'public_id', 'path', 'caption', 'position', 'idempotency_key', 'request_hash'];

    protected $hidden = ['path', 'request_hash', 'idempotency_key'];

    protected static function booted(): void
    {
        static::creating(fn (BusinessPhoto $photo) => $photo->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
