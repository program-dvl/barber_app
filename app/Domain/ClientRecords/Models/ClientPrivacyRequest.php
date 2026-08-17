<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\PlatformAccess\Models\Membership;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientPrivacyRequest extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'client_id', 'reviewed_by_membership_id', 'export_attachment_id', 'type', 'status', 'request_details', 'decision_reason', 'result_summary', 'version', 'requested_at', 'due_at', 'reviewed_at', 'completed_at'];

    protected static function booted(): void
    {
        static::creating(fn (ClientPrivacyRequest $request) => $request->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['request_details' => 'encrypted:array', 'result_summary' => 'array', 'version' => 'integer', 'requested_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'reviewed_by_membership_id');
    }

    public function exportAttachment(): BelongsTo
    {
        return $this->belongsTo(ClientAttachment::class, 'export_attachment_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
