<?php

namespace App\Domain\PlatformAccess\Models;

use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Models\BookingSlugAlias;
use App\Domain\BusinessConfiguration\Models\ConfigurationImport;
use App\Domain\BusinessConfiguration\Models\OnboardingSession;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientFormTemplate;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\PlatformAccess\Enums\BusinessStatus;
use App\Domain\Reporting\Models\ReportExport;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    protected $fillable = [
        'public_id',
        'name',
        'slug',
        'booking_slug',
        'business_type',
        'country_code',
        'locale',
        'currency_code',
        'time_zone',
        'week_starts_on',
        'appointment_interval_minutes',
        'tax_posture',
        'phone',
        'email',
        'website_url',
        'social_links',
        'address',
        'map_url',
        'default_cancellation_policy',
        'terms_url',
        'privacy_url',
        'logo_path',
        'cover_image_path',
        'configuration_published_at',
        'online_booking_enabled',
        'online_staff_preference',
        'online_price_display',
        'online_new_client_rule',
        'staff_gender_request_enabled',
        'cancellation_cutoff_minutes',
        'waitlist_offer_batch_size',
        'public_link_ttl_minutes',
        'public_booking_policy_version',
        'status',
        'suspended_at',
        'closed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Business $business): void {
            $business->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'social_links' => 'array',
            'week_starts_on' => 'integer',
            'appointment_interval_minutes' => 'integer',
            'configuration_published_at' => 'immutable_datetime',
            'online_booking_enabled' => 'boolean',
            'staff_gender_request_enabled' => 'boolean',
            'cancellation_cutoff_minutes' => 'integer',
            'waitlist_offer_batch_size' => 'integer',
            'public_link_ttl_minutes' => 'integer',
            'public_booking_policy_version' => 'integer',
            'suspended_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): BusinessFactory
    {
        return BusinessFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function staffs(): HasMany
    {
        return $this->staffProfiles();
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(BusinessSubscription::class);
    }

    public function billingCheckoutAttempts(): HasMany
    {
        return $this->hasMany(BillingCheckoutAttempt::class);
    }

    public function onboardingSession(): HasOne
    {
        return $this->hasOne(OnboardingSession::class);
    }

    public function bookingSlugAliases(): HasMany
    {
        return $this->hasMany(BookingSlugAlias::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function physicalResources(): HasMany
    {
        return $this->hasMany(PhysicalResource::class);
    }

    public function configurationImports(): HasMany
    {
        return $this->hasMany(ConfigurationImport::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(InventoryProduct::class);
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function clientFormTemplates(): HasMany
    {
        return $this->hasMany(ClientFormTemplate::class);
    }

    public function communicationTemplates(): HasMany
    {
        return $this->hasMany(CommunicationTemplate::class);
    }

    public function communicationMessages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class);
    }

    public function isActive(): bool
    {
        return $this->status === BusinessStatus::Active;
    }
}
