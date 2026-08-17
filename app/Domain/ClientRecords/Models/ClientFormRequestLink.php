<?php

namespace App\Domain\ClientRecords\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientFormRequestLink extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'client_form_request_id', 'token_hash', 'expires_at', 'used_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ClientFormRequest::class, 'client_form_request_id');
    }
}
