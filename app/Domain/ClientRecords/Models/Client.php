<?php

namespace App\Domain\ClientRecords\Models;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\MoneyCommerce\Models\Deposit;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Client extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id', 'public_id', 'name', 'normalized_name', 'email', 'normalized_email', 'mobile',
        'normalized_mobile', 'date_of_birth', 'preferred_staff_profile_id', 'preferences',
        'communication_preferences', 'referral_source', 'marketing_status', 'status',
        'merged_into_client_id', 'version', 'anonymized_at',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Client $client) => $client->public_id ??= (string) Str::ulid());
    }

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date', 'preferences' => 'encrypted:array',
            'communication_preferences' => 'encrypted:array', 'version' => 'integer',
            'anonymized_at' => 'immutable_datetime',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderByDesc('starts_at_utc');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->orderByDesc('created_at');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ClientConsent::class)->orderByDesc('occurred_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClientAttachment::class)->orderByDesc('created_at');
    }

    public function formRequests(): HasMany
    {
        return $this->hasMany(ClientFormRequest::class)->orderByDesc('requested_at');
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(ClientFormSubmission::class)->orderByDesc('submitted_at');
    }

    public function privacyRequests(): HasMany
    {
        return $this->hasMany(ClientPrivacyRequest::class)->orderByDesc('requested_at');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class)->orderByDesc('created_at');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class)->orderByDesc('completed_at');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class)->orderByDesc('created_at');
    }

    public function tags(): BelongsToMany
    {
        $relation = $this->belongsToMany(ClientTag::class, 'client_tag_assignments')->withPivot('business_id')->withTimestamps();

        return $this->business_id ? $relation->wherePivot('business_id', $this->business_id) : $relation;
    }

    public function preferredServices(): BelongsToMany
    {
        $relation = $this->belongsToMany(Service::class, 'client_preferred_services')->withPivot('business_id')->withTimestamps();

        return $this->business_id ? $relation->wherePivot('business_id', $this->business_id) : $relation;
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'preferred_staff_profile_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_client_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
