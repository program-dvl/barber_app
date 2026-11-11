<?php

namespace App\Domain\Marketplace\Models;

use App\Domain\SchedulingOperations\Models\Appointment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerBooking extends Model
{
    protected $table = 'marketplace_bookings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['messages_paused' => 'boolean'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'booking_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'booking_id')->latestOfMany('id');
    }
}
