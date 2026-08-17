<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\PlatformAccess\Models\Membership;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDuplicateCandidate extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'first_client_id', 'second_client_id', 'surviving_client_id', 'reviewed_by_membership_id', 'status', 'confidence', 'reasons', 'preview_snapshot', 'detected_at', 'reviewed_at'];

    protected function casts(): array
    {
        return ['confidence' => 'integer', 'reasons' => 'array', 'preview_snapshot' => 'array', 'detected_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime'];
    }

    public function firstClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'first_client_id');
    }

    public function secondClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'second_client_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'reviewed_by_membership_id');
    }
}
