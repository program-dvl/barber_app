<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Customer extends Authenticatable
{
    protected $table = 'marketplace_customers';

    protected $guarded = ['id'];

    protected $hidden = ['remember_token', 'photo_path'];

    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('marketplace.profile.photo', ['v' => substr(hash('sha256', $this->photo_path), 0, 16)]) : null;
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $customer) => $customer->public_id ??= (string) Str::ulid());
    }
}
