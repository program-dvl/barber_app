<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class OnboardingSession extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'current_step', 'completed_steps', 'started_at', 'last_saved_at', 'previewed_at', 'published_at'];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'started_at' => 'immutable_datetime',
            'last_saved_at' => 'immutable_datetime',
            'previewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
