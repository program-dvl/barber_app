<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClientFormTemplate extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'name', 'purpose', 'status', 'current_version', 'request_before_appointment'];

    protected static function booted(): void
    {
        static::creating(fn (ClientFormTemplate $template) => $template->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['current_version' => 'integer', 'request_before_appointment' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ClientFormTemplateVersion::class)->orderByDesc('version');
    }

    public function services(): BelongsToMany
    {
        $relation = $this->belongsToMany(Service::class, 'client_form_service')->withPivot('business_id')->withTimestamps();

        return $this->business_id ? $relation->wherePivot('business_id', $this->business_id) : $relation;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
