<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class OnboardingSession extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'schema_version', 'current_step', 'completed_steps', 'answers', 'generated_data',
        'started_at', 'last_saved_at', 'personalized_at', 'guided_completed_at', 'previewed_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'answers' => 'array',
            'generated_data' => 'array',
            'schema_version' => 'integer',
            'started_at' => 'immutable_datetime',
            'last_saved_at' => 'immutable_datetime',
            'personalized_at' => 'immutable_datetime',
            'guided_completed_at' => 'immutable_datetime',
            'previewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
