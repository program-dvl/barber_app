<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\OnboardingSession;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Regional\CountryCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StarterWorkspaceProvisioner
{
    public function __construct(
        private readonly OnboardingManager $onboarding,
        private readonly CountryCatalog $countries,
        private readonly AuditWriter $audit,
    ) {}

    /** @param array<string, mixed> $answers */
    public function provision(Business $business, Membership $membership, User $actor, array $answers): Business
    {
        abort_unless((int) $membership->business_id === (int) $business->id && (int) $membership->user_id === (int) $actor->id, 403);
        if ($business->onboardingSession?->guided_completed_at) {
            return $business->fresh();
        }

        DB::transaction(function () use ($business, $membership, $actor, $answers): array {
            $business = Business::query()->lockForUpdate()->findOrFail($business->getKey());
            $session = OnboardingSession::query()->where('business_id', $business->getKey())->lockForUpdate()->firstOrFail();

            if ($session->guided_completed_at) {
                return $session->generated_data ?? [];
            }

            if ($business->configuration_published_at || Appointment::query()->where('business_id', $business->id)->exists()
                || Sale::query()->where('business_id', $business->id)->exists()) {
                throw ValidationException::withMessages(['onboarding' => 'This business already has operational history. Review its current setup instead of restoring starter defaults.']);
            }
            $types = config('business-onboarding.business_types');
            $typeKey = (string) ($answers['business_type'] ?? '');
            $type = $types[$typeKey] ?? null;
            if (! $type) {
                throw ValidationException::withMessages(['business_type' => 'Choose a supported business type.']);
            }

            $country = strtoupper((string) ($answers['country_code'] ?? ''));
            $countryDefaults = $this->countries->defaults()[$country] ?? null;
            if (! $countryDefaults) {
                throw ValidationException::withMessages(['country_code' => 'Choose a recognized country.']);
            }
            $currency = strtoupper((string) ($countryDefaults['currency'] ?: 'USD'));
            $timeZone = (string) ($answers['time_zone'] ?? '');
            if (! in_array($timeZone, $countryDefaults['time_zones'], true)) {
                throw ValidationException::withMessages(['time_zone' => 'Choose a time zone for the selected country.']);
            }

            $scheduleKey = (string) ($answers['schedule_preset'] ?? 'tuesday_saturday');
            $schedule = config('business-onboarding.schedule_presets.'.$scheduleKey);
            if (! $schedule) {
                throw ValidationException::withMessages(['schedule_preset' => 'Choose a starter schedule.']);
            }

            $preparedCommerce = CommerceSetting::query()->where('business_id', $business->id)->first();
            $business->fill([
                'business_type' => $typeKey,
                'description' => $business->description ?: $type['description'],
                'country_code' => $country,
                'locale' => $this->suggestLocale($country),
                'currency_code' => $business->currency_code ?: $currency,
                'time_zone' => $timeZone,
                'week_starts_on' => in_array($country, ['CA', 'US'], true) ? 7 : 1,
                'appointment_interval_minutes' => $business->appointment_interval_minutes ?: 15,
                'tax_posture' => $business->tax_posture ?: ($preparedCommerce?->default_tax_rate_bps ? ($preparedCommerce->tax_inclusive ? 'inclusive' : 'exclusive') : 'not_registered'),
                'phone' => (string) $answers['phone'],
                'email' => $business->email ?: $actor->email,
                'address' => (string) $answers['address'],
                'default_cancellation_policy' => $business->default_cancellation_policy
                    ?: 'Please give at least 24 hours notice if you need to cancel or reschedule.',
                'terms_url' => $business->terms_url ?: route('terms.show'),
                'privacy_url' => $business->privacy_url ?: route('policy.show'),
                'brand_color' => $business->brand_color ?: $type['accent'],
                'online_booking_enabled' => false,
                'online_staff_preference' => 'any_or_preferred',
                'online_price_display' => 'service_setting',
                'online_new_client_rule' => 'allow',
                'cancellation_cutoff_minutes' => $preparedCommerce?->cancellation_cutoff_minutes ?? 1440,
            ]);
            $business->booking_slug ??= $this->availableBookingSlug($business);
            $business->save();

            CommerceSetting::query()->firstOrCreate(
                ['business_id' => $business->getKey()],
                ['currency_code' => $business->currency_code, 'tax_inclusive' => false, 'default_tax_rate_bps' => 0, 'cancellation_cutoff_minutes' => 1440],
            );

            $location = $business->locations()->where('is_active', true)->oldest('id')->first()
                ?? $business->locations()->oldest('id')->first();
            if (! $location) {
                $location = $business->locations()->create([
                    'name' => $business->name, 'time_zone' => $timeZone, 'status' => 'active', 'is_active' => true,
                ]);
            }
            $location->forceFill([
                'name' => $location->name ?: $business->name,
                'time_zone' => $timeZone,
                'status' => 'active',
                'is_active' => true,
                'address' => (string) $answers['address'],
                'phone' => (string) $answers['phone'],
                'email' => $location->email ?: $actor->email,
            ])->save();
            $membership->locations()->syncWithoutDetaching([
                $location->getKey() => ['business_id' => $business->getKey()],
            ]);

            if (! $location->hours()->exists()) {
                foreach ($schedule['days'] as $day) {
                    LocationHour::query()->create([
                        'business_id' => $business->getKey(), 'location_id' => $location->getKey(),
                        'day_of_week' => $day, 'opens_at' => $schedule['opens_at'], 'closes_at' => $schedule['closes_at'], 'sequence' => 1,
                    ]);
                }
            }

            $ownerBookable = (bool) ($answers['owner_bookable'] ?? true);
            $staff = $membership->staffProfile()->first()
                ?? $business->staffProfiles()->where('user_id', $actor->getKey())->first();
            if (! $staff) {
                $staff = StaffProfile::query()->create([
                    'business_id' => $business->getKey(), 'membership_id' => $membership->getKey(), 'user_id' => $actor->getKey(),
                    'display_name' => $actor->name, 'email' => strtolower($actor->email),
                    'title' => $ownerBookable ? 'Owner & professional' : 'Owner', 'status' => 'active', 'online_visible' => $ownerBookable,
                ]);
            } else {
                $staff->forceFill([
                    'membership_id' => $staff->membership_id ?: $membership->getKey(),
                    'user_id' => $staff->user_id ?: $actor->getKey(),
                    'online_visible' => $ownerBookable,
                ])->save();
            }
            $staff->locations()->syncWithoutDetaching([
                $location->getKey() => ['business_id' => $business->getKey()],
            ]);
            if ($ownerBookable && ! $staff->availabilityRules()->where('kind', 'working')->exists()) {
                foreach ($schedule['days'] as $day) {
                    StaffAvailabilityRule::query()->create([
                        'business_id' => $business->getKey(), 'staff_profile_id' => $staff->getKey(), 'location_id' => $location->getKey(),
                        'kind' => 'working', 'day_of_week' => $day, 'starts_at' => $schedule['opens_at'], 'ends_at' => $schedule['closes_at'], 'sequence' => 1,
                    ]);
                }
            }

            $selectedKeys = array_values(array_unique($answers['service_keys'] ?? []));
            $templates = collect($type['services'])->keyBy('key');
            if (collect($selectedKeys)->contains(fn (string $key): bool => ! $templates->has($key))) {
                throw ValidationException::withMessages(['service_keys' => 'Choose supported starter services.']);
            }

            $serviceIds = [];
            $categoryIds = [];
            if (! $business->services()->where('kind', 'service')->exists()) {
                // Prepare the whole small profile, including categories without a selected service.
                // Reuse tenant records without resetting their names, order or archive preference.
                $categories = ServiceCategory::query()->where('business_id', $business->getKey())->get()
                    ->keyBy(fn (ServiceCategory $category) => Str::lower($category->name));
                $nextOrder = (int) ($categories->max('display_order') ?? 0);
                $categoryNames = collect($type['categories'] ?? [])
                    ->merge($templates->pluck('category'))->unique(fn (string $name) => Str::lower($name));
                foreach ($categoryNames as $name) {
                    $normalized = Str::lower($name);
                    $category = $categories->get($normalized);
                    if (! $category) {
                        $category = ServiceCategory::query()->create([
                            'business_id' => $business->getKey(), 'name' => $name,
                            'display_order' => ++$nextOrder, 'is_active' => true,
                        ]);
                        $categories->put($normalized, $category);
                        $categoryIds[] = $category->public_id;
                    }
                }
                foreach ($selectedKeys as $key) {
                    $template = $templates->get($key);
                    $category = $categories->get(Str::lower($template['category']));
                    $service = Service::query()->create([
                        'business_id' => $business->getKey(), 'service_category_id' => $category->getKey(), 'kind' => 'service',
                        'name' => $template['name'], 'description' => $template['description'], 'price_type' => 'fixed',
                        'price_minor' => $this->starterPriceMinor($business->currency_code, (float) $template['price_factor']),
                        'currency_code' => $business->currency_code, 'tax_inclusive' => false,
                        'duration_minutes' => $template['duration'], 'processing_minutes' => 0, 'cleanup_minutes' => 0,
                        'minimum_notice_minutes' => 60, 'maximum_advance_days' => 90,
                        'deposit_type' => 'none', 'deposit_value' => 0, 'client_eligibility' => 'all',
                        'consultation_required' => str_contains($key, 'consult'), 'online_visible' => $category->is_active, 'is_active' => $category->is_active,
                    ]);
                    ServiceSegment::query()->create([
                        'business_id' => $business->getKey(), 'service_id' => $service->getKey(), 'kind' => 'active',
                        'sequence' => 1, 'duration_minutes' => $template['duration'], 'occupies_staff' => true,
                    ]);
                    $service->locations()->sync([
                        $location->getKey() => ['business_id' => $business->getKey(), 'is_eligible' => true],
                    ]);
                    if ($ownerBookable) {
                        StaffServiceAssignment::query()->create([
                            'business_id' => $business->getKey(), 'staff_profile_id' => $staff->getKey(), 'service_id' => $service->getKey(),
                            'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
                        ]);
                    }
                    $serviceIds[] = $service->public_id;
                }
            }

            $generated = [
                'version' => config('business-onboarding.schema_version'),
                'location' => $location->public_id,
                'owner_staff' => $staff->public_id,
                'services' => $serviceIds,
                'categories' => $categoryIds,
                'starter_schedule' => $scheduleKey,
            ];
            $completedSteps = array_values(array_unique([
                ...($session->completed_steps ?? []), 'business_details', 'hours', 'staff', 'staff_availability', 'services', 'booking_rules',
            ]));
            $session->forceFill([
                'schema_version' => config('business-onboarding.schema_version'),
                'answers' => $answers,
                'generated_data' => $generated,
                'completed_steps' => $completedSteps,
                'current_step' => 'preview',
                'personalized_at' => now(),
                'guided_completed_at' => now(),
                'last_saved_at' => now(),
            ])->save();

            $this->audit->write(
                action: 'onboarding.starter_workspace.created', business: $business, actor: $actor, target: $session,
                reason: 'Owner confirmed adaptive onboarding recommendations.',
                after: ['business_type' => $typeKey, 'location' => $location->public_id, 'owner_bookable' => $ownerBookable, 'service_count' => count($serviceIds), 'category_count' => count($categoryIds), 'schema_version' => $session->schema_version],
                source: 'onboarding',
            );

            return $generated;
        }, 3);

        return $business->fresh();
    }

    private function availableBookingSlug(Business $business): string
    {
        $base = Str::slug($business->name) ?: 'business';
        $base = Str::limit($base, 68, '');
        if (! Business::query()->where('booking_slug', $base)->whereKeyNot($business->getKey())->exists()) {
            return $base;
        }

        return $base.'-'.Str::lower(substr((string) $business->public_id, -6));
    }

    private function suggestLocale(string $country): string
    {
        return match ($country) {
            'DE' => 'de-DE',
            'FR' => 'fr-FR',
            default => 'en-'.$country,
        };
    }

    private function starterPriceMinor(string $currency, float $factor): int
    {
        $base = (int) config('business-onboarding.starter_price_major.'.strtoupper($currency), 35);

        return max(100, (int) round($base * $factor) * 100);
    }
}
