<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\ClientRecords\Support\ImmutableRecord;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientFormTemplateVersion extends Model
{
    use BelongsToBusiness;
    use ImmutableRecord;

    protected $fillable = ['business_id', 'client_form_template_id', 'created_by_staff_profile_id', 'version', 'title', 'introduction', 'fields', 'published_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'fields' => 'array', 'published_at' => 'immutable_datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ClientFormTemplate::class, 'client_form_template_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'created_by_staff_profile_id');
    }
}
