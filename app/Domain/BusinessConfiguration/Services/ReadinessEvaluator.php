<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Data\ReadinessResult;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Business;
use App\Support\Regional\CountryCatalog;
use Carbon\CarbonImmutable;

class ReadinessEvaluator
{
    public function __construct(private readonly LocalHoursResolver $hours, private readonly StaffAvailabilityResolver $availability, private readonly CountryCatalog $countries) {}

    public function evaluate(Business $business): ReadinessResult
    {
        return $this->inspect($business)['publication'];
    }

    /** Current configuration, shared by operational progress and publication. No persisted completion flags. */
    public function inspect(Business $business): array
    {
        $business->load(['locations.hours', 'locations.scheduleExceptions', 'staffProfiles.locations',
            'staffProfiles.availabilityRules', 'services.locations', 'services.staffAssignments',
            'services.resourceRequirements.resource', 'onboardingSession']);
        $blockers = [];
        foreach (['name' => 'Add your business name.', 'country_code' => 'Choose the business country.',
            'currency_code' => 'Choose a currency.', 'time_zone' => 'Choose a business time zone.'] as $field => $message) {
            if (! filled($business->{$field})) {
                $blockers[] = $this->item('profile.'.$field, $message, 'business_details');
            }
        }
        if (filled($business->country_code) && ! array_key_exists($business->country_code, $this->countries->countries())) {
            $blockers[] = $this->item('profile.country_code', 'Choose a recognized business country.', 'business_details');
        }
        if (filled($business->currency_code) && ! array_key_exists($business->currency_code, $this->countries->currencies())) {
            $blockers[] = $this->item('profile.currency_code', 'Choose a recognized currency.', 'business_details');
        }
        $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC);
        if (filled($business->time_zone) && ! in_array($business->time_zone, $zones, true)) {
            $blockers[] = $this->item('profile.time_zone', 'Choose a valid business time zone.', 'business_details');
        }
        $locations = $business->locations->filter(fn ($l) => $l->is_active && $l->status === 'active');
        $openLocations = $locations->filter(fn ($l) => in_array($l->time_zone, $zones, true)
            && $this->dates($l)->contains(fn ($date) => $this->hours->windows($l, $date) !== []));
        if ($locations->isEmpty()) {
            $blockers[] = $this->item('locations.active', 'Add an active location.', 'hours');
        } elseif ($openLocations->isEmpty()) {
            $blockers[] = $this->item('locations.hours', 'Set opening hours at an active location in the next four weeks.', 'hours');
        }
        $staff = $business->staffProfiles->where('status', 'active');
        if ($staff->isEmpty()) {
            $blockers[] = $this->item('staff.active', 'Add a team member who can take appointments.', 'staff');
        }
        $services = $business->services->filter(fn ($s) => $s->kind === 'service' && $s->is_active
            && (! $s->effective_from || $s->effective_from->lte(now())) && (! $s->effective_until || $s->effective_until->gt(now())));
        $valid = $services->filter(fn ($s) => $s->duration_minutes > 0 && $s->price_minor !== null && $s->price_minor >= 0
            && filled($s->currency_code) && $s->currency_code === $business->currency_code);
        if ($services->isEmpty()) {
            $blockers[] = $this->item('services.active', 'Keep at least one active service, or add your own.', 'services');
        } elseif ($valid->isEmpty()) {
            $blockers[] = $this->item('services.values', 'Review service prices, currency and duration.', 'services');
        }
        $paths = $valid->filter(fn ($s) => $this->hasValidDeliveryPath($s, $staff, $openLocations, false));
        $online = $valid->where('online_visible', true)->filter(fn ($s) => $this->hasValidDeliveryPath($s, $staff, $openLocations, true));
        if ($paths->isEmpty()) {
            $blockers[] = $this->item('staff.availability', 'Match a service to a qualified team member with working hours inside your opening hours.', 'staff_availability');
        }
        if (! in_array((int) $business->appointment_interval_minutes, [5, 10, 15, 20, 30, 60], true)) {
            $blockers[] = $this->item('rules.appointment_interval', 'Choose an appointment interval.', 'booking_rules');
        }
        if ($business->cancellation_cutoff_minutes === null || $business->cancellation_cutoff_minutes < 0 || $business->cancellation_cutoff_minutes > 43200) {
            $blockers[] = $this->item('rules.cancellation', 'Review the cancellation window.', 'booking_rules');
        }
        $public = $blockers;
        foreach (['business_type' => 'Choose a business type.', 'locale' => 'Choose a language and region.',
            'week_starts_on' => 'Choose the first day of the week.', 'tax_posture' => 'Review your tax treatment.',
            'phone' => 'Add a public phone number.', 'email' => 'Add a public email address.', 'address' => 'Add the business address.',
            'default_cancellation_policy' => 'Review your cancellation policy.', 'terms_url' => 'Add a terms link.',
            'privacy_url' => 'Add a privacy link.', 'booking_slug' => 'Choose a booking link.'] as $field => $message) {
            if ($business->{$field} === null || $business->{$field} === '') {
                $public[] = $this->item('profile.'.$field, $message, 'business_details');
            }
        }
        if ($online->isEmpty()) {
            $public[] = $this->item('services.delivery_path', 'Make a service and its qualified team member available online with matching hours.', 'services');
        }
        if (! $business->onboardingSession?->previewed_at) {
            $public[] = $this->item('preview.required', 'Review the booking preview before going live.', 'preview');
        }
        $improvements = [];
        foreach (['logo_path' => 'Add your logo.', 'cover_image_path' => 'Add a photo of your business.', 'website_url' => 'Add your website.'] as $field => $message) {
            if (! filled($business->{$field})) {
                $improvements[] = $this->item('branding.'.$field, $message, 'business_details');
            }
        }

        return [
            'operational' => new ReadinessResult($blockers, $improvements, $blockers[0]['step'] ?? null, $blockers === []),
            'publication' => new ReadinessResult($public, $improvements, $public[0]['step'] ?? null, $public === []),
            'bookable_service_ids' => $paths->pluck('public_id')->all(),
            'online_service_ids' => $online->pluck('public_id')->all(),
            'counts' => ['locations' => $locations->count(), 'services' => $services->count(), 'valid_services' => $valid->count(),
                'staff' => $staff->count(), 'bookable_services' => $paths->count(), 'online_services' => $online->count(),
                'services_needing_attention' => $services->count() - $paths->count()],
        ];
    }

    private function dates($location)
    {
        $today = CarbonImmutable::today($location->time_zone);

        return collect(range(0, 27))->map(fn ($day) => $today->addDays($day));
    }

    private function hasValidDeliveryPath(Service $service, $staff, $locations, bool $online): bool
    {
        foreach ($locations as $location) {
            if (! $service->locations->contains(fn ($l) => $l->id === $location->id && $l->pivot->is_eligible)) {
                continue;
            }
            if (! $service->resourceRequirements->every(fn ($r) => $r->resource && $r->resource->business_id === $service->business_id
                && $r->resource->location_id === $location->id && $r->resource->is_active && $r->resource->quantity >= $r->quantity)) {
                continue;
            }
            foreach ($service->staffAssignments->sortByDesc(fn ($a) => $a->effective_from?->getTimestamp() ?? 0) as $assignment) {
                $person = $staff->firstWhere('id', $assignment->staff_profile_id);
                if (! $assignment->is_active || ! $assignment->is_qualified || ! $person
                    || ($online && (! $assignment->online_visible || ! $person->online_visible))
                    || ! $person->locations->contains('id', $location->id)) {
                    continue;
                }
                $duration = ($assignment->duration_minutes ?? $service->duration_minutes)
                    + ($assignment->processing_minutes ?? $service->processing_minutes) + ($assignment->cleanup_minutes ?? $service->cleanup_minutes);
                if (($assignment->duration_minutes ?? $service->duration_minutes) < 1 || ($assignment->price_minor ?? $service->locations->firstWhere('id', $location->id)->pivot->price_minor ?? $service->price_minor) < 0) {
                    continue;
                }
                foreach ($this->dates($location) as $date) {
                    if (($assignment->effective_from && $assignment->effective_from->gt($date->endOfDay()))
                        || ($assignment->effective_until && $assignment->effective_until->lte($date->startOfDay()))) {
                        continue;
                    }
                    foreach ($this->hours->windows($location, $date) as $opening) {
                        foreach ($this->availability->windows($person, $location, $date) as $working) {
                            $start = max($opening['opens_at'], $working['opens_at']);
                            $end = min($opening['closes_at'], $working['closes_at']);
                            $starts = CarbonImmutable::parse($date->toDateString().' '.$start, $location->time_zone)->utc();
                            $ends = CarbonImmutable::parse($date->toDateString().' '.$end, $location->time_zone)->utc();
                            $starts = $starts->max(CarbonImmutable::now());
                            foreach ([$assignment->effective_from, $service->effective_from] as $from) {
                                if ($from) {
                                    $starts = $starts->max($from);
                                }
                            }
                            foreach ([$assignment->effective_until, $service->effective_until] as $until) {
                                if ($until) {
                                    $ends = $ends->min($until);
                                }
                            }
                            $latest = $service->staffAssignments->filter(fn ($a) => $a->staff_profile_id === $person->id && $a->is_active && $a->is_qualified
                                && (! $a->effective_from || $a->effective_from->lte($starts)) && (! $a->effective_until || $a->effective_until->gt($starts)))
                                ->sortByDesc(fn ($a) => $a->effective_from?->getTimestamp() ?? 0)->first();
                            if ($latest?->id === $assignment->id && $starts->lt($ends) && $starts->diffInMinutes($ends) >= $duration) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function item(string $code, string $message, string $step): array
    {
        return compact('code', 'message', 'step');
    }
}
