<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Data\ReadinessResult;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Business;

class ReadinessEvaluator
{
    public function evaluate(Business $business): ReadinessResult
    {
        $blockers = [];
        $improvements = [];

        $requiredProfile = [
            'business_type' => 'Choose a business type.', 'country_code' => 'Choose the business country.',
            'locale' => 'Choose a locale.', 'currency_code' => 'Choose a currency.', 'time_zone' => 'Choose a business time zone.',
            'week_starts_on' => 'Choose the first day of the week.', 'tax_posture' => 'Choose whether prices include tax.',
            'phone' => 'Add a public phone number.', 'email' => 'Add a public email address.', 'address' => 'Add the business address.',
            'default_cancellation_policy' => 'Add a cancellation policy.', 'terms_url' => 'Add a terms link.',
            'privacy_url' => 'Add a privacy link.', 'booking_slug' => 'Choose a booking link.',
        ];
        foreach ($requiredProfile as $field => $message) {
            if ($business->{$field} === null || $business->{$field} === '') {
                $blockers[] = $this->item('profile.'.$field, $message, 'business_details');
            }
        }
        if (! $business->appointment_interval_minutes) {
            $blockers[] = $this->item('rules.appointment_interval', 'Choose an appointment interval.', 'booking_rules');
        }

        $locations = $business->locations()->where('is_active', true)->where('status', 'active')->get();
        if ($locations->isEmpty()) {
            $blockers[] = $this->item('locations.active', 'Add an active location.', 'hours');
        } elseif (! $locations->contains(fn ($location) => $location->hours()->exists())) {
            $blockers[] = $this->item('locations.hours', 'Add normal opening hours for an active location.', 'hours');
        }

        $activeStaff = $business->staffProfiles()->where('status', 'active')->get();
        if ($activeStaff->isEmpty()) {
            $blockers[] = $this->item('staff.active', 'Add at least one active staff profile.', 'staff');
        } elseif (! $activeStaff->contains(fn ($staff) => $staff->locations()->exists() && $staff->availabilityRules()->where('kind', 'working')->exists())) {
            $blockers[] = $this->item('staff.availability', 'Assign an active staff member to a location and add working hours.', 'staff_availability');
        }

        $services = $business->services()->where('kind', 'service')->where('is_active', true)->where('online_visible', true)->get();
        if ($services->isEmpty()) {
            $blockers[] = $this->item('services.bookable', 'Add an active service visible online.', 'services');
        } elseif (! $services->contains(fn (Service $service) => $this->hasValidDeliveryPath($service))) {
            $blockers[] = $this->item('services.delivery_path', 'Give a visible service an eligible location, qualified staff member, and enough resource quantity.', 'services');
        }

        $session = $business->onboardingSession;
        if (! $session?->previewed_at) {
            $blockers[] = $this->item('preview.required', 'Preview the booking page on mobile and desktop.', 'preview');
        }

        foreach ([
            'logo_path' => 'Add a logo to make the booking page recognizable.',
            'cover_image_path' => 'Add a cover image to strengthen the booking page.',
            'website_url' => 'Add a website link.',
        ] as $field => $message) {
            if (! $business->{$field}) {
                $improvements[] = $this->item('branding.'.$field, $message, 'business_details');
            }
        }
        if (! $business->configurationImports()->where('status', 'completed')->exists()) {
            $improvements[] = $this->item('import.optional', 'Import existing records if you are switching systems.', 'import');
        }

        return new ReadinessResult($blockers, $improvements, $blockers[0]['step'] ?? null, $blockers === []);
    }

    private function hasValidDeliveryPath(Service $service): bool
    {
        $eligibleLocationIds = $service->locations()->wherePivot('is_eligible', true)->pluck('locations.id');
        if ($eligibleLocationIds->isEmpty()) {
            return false;
        }

        $hasStaff = $service->staffAssignments()
            ->where('is_active', true)->where('is_qualified', true)->where('online_visible', true)
            ->get()
            ->contains(function ($assignment) use ($eligibleLocationIds): bool {
                $staff = $assignment->business->staffProfiles()->find($assignment->staff_profile_id);

                return $staff
                    && $staff->status === 'active'
                    && $staff->locations()->whereIn('locations.id', $eligibleLocationIds)->exists()
                    && $staff->availabilityRules()->where('kind', 'working')->exists();
            });
        if (! $hasStaff) {
            return false;
        }

        return $service->resourceRequirements()->get()->every(function ($requirement): bool {
            $resource = $requirement->business->physicalResources()->find($requirement->physical_resource_id);

            return $resource && $resource->is_active && $resource->quantity >= $requirement->quantity;
        });
    }

    /** @return array{code:string,message:string,step:string} */
    private function item(string $code, string $message, string $step): array
    {
        return compact('code', 'message', 'step');
    }
}
