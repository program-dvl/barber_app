<?php

namespace App\Domain\ClientRecords\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAttachmentAccessLink extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'client_attachment_id', 'token_hash', 'purpose', 'expires_at', 'revoked_at', 'last_accessed_at', 'access_count'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'last_accessed_at' => 'immutable_datetime', 'access_count' => 'integer'];
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(ClientAttachment::class, 'client_attachment_id');
    }
}
