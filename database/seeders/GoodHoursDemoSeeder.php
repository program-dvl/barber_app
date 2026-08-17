<?php

namespace Database\Seeders;

use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\OnboardingSession;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\ServiceResourceRequirement;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\ClientRecords\Services\ClientFormService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoodHoursDemoSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::query()->updateOrCreate(['slug' => 'good-hours-demo-tenant'], [
            'name' => 'Pine & Palm Studio', 'booking_slug' => 'pine-palm-demo', 'business_type' => 'Barber shop and salon',
            'country_code' => 'IN', 'locale' => 'en-IN', 'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata',
            'week_starts_on' => 1, 'appointment_interval_minutes' => 15, 'tax_posture' => 'inclusive',
            'phone' => '+91 90000 00000', 'email' => 'hello@pine-palm.example.test', 'address' => '12 Demo Market Road, Bengaluru',
            'default_cancellation_policy' => 'Please give at least 24 hours notice when cancelling.',
            'terms_url' => 'https://example.test/terms', 'privacy_url' => 'https://example.test/privacy', 'configuration_published_at' => now(),
        ]);
        app(BusinessAccessBootstrapper::class)->bootstrap($business);
        app(ClientFormService::class)->seedStarterTemplates($business->id);
        $owner = User::query()->firstOrCreate(['email' => 'owner@pine-palm.example.test'], [
            'name' => 'Demo Owner', 'password' => Hash::make(Str::random(40)), 'email_verified_at' => now(),
        ]);
        $membership = Membership::query()->firstOrCreate(['business_id' => $business->id, 'user_id' => $owner->id], [
            'status' => 'active', 'joined_at' => now(),
        ]);
        app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $owner, 'Production-like demo owner.');
        $trialPlan = BillingPlan::query()->where('is_trial_default', true)->where('is_active', true)->firstOrFail();
        BusinessSubscription::query()->firstOrCreate(['business_id' => $business->id], [
            'billing_plan_id' => $trialPlan->id,
            'provider' => config('billing.provider'),
            'status' => SubscriptionStatus::Trialing,
            'restriction_level' => RestrictionLevel::None,
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays(config('billing.trial_days')),
        ]);

        $location = Location::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Indiranagar Studio'], [
            'time_zone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true,
            'address' => '12 Demo Market Road, Bengaluru', 'phone' => '+91 90000 00000', 'email' => 'studio@pine-palm.example.test',
        ]);
        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            LocationHour::query()->firstOrCreate(['location_id' => $location->id, 'day_of_week' => $day, 'sequence' => 1, 'effective_from' => null], [
                'business_id' => $business->id, 'opens_at' => '09:00', 'closes_at' => '19:00',
            ]);
        }
        $chair = PhysicalResource::query()->firstOrCreate(['business_id' => $business->id, 'location_id' => $location->id, 'name' => 'Barber chairs'], [
            'type' => 'barber_chair', 'quantity' => 3, 'is_active' => true,
        ]);
        $category = ServiceCategory::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Cuts']);
        $service = Service::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Signature cut'], [
            'service_category_id' => $category->id, 'kind' => 'service', 'price_minor' => 75000, 'currency_code' => 'INR',
            'tax_category' => 'salon_service', 'tax_inclusive' => true, 'duration_minutes' => 40, 'processing_minutes' => 0,
            'cleanup_minutes' => 5, 'deposit_type' => 'percentage', 'deposit_value' => 2500, 'minimum_notice_minutes' => 60,
            'maximum_advance_days' => 90, 'is_active' => true, 'online_visible' => true,
        ]);
        $service->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id, 'is_eligible' => true]]);
        $segment = ServiceSegment::query()->firstOrCreate(['service_id' => $service->id, 'sequence' => 1], [
            'business_id' => $business->id, 'kind' => 'active', 'duration_minutes' => 40, 'occupies_staff' => true,
        ]);
        ServiceResourceRequirement::query()->firstOrCreate(['service_id' => $service->id, 'service_segment_id' => $segment->id, 'physical_resource_id' => $chair->id], [
            'business_id' => $business->id, 'quantity' => 1,
        ]);
        $staff = StaffProfile::query()->firstOrCreate(['business_id' => $business->id, 'email' => 'aria@pine-palm.example.test'], [
            'display_name' => 'Aria', 'title' => 'Senior barber', 'status' => 'active', 'online_visible' => true,
        ]);
        $staff->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
        StaffServiceAssignment::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id], [
            'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
        ]);
        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            StaffAvailabilityRule::query()->firstOrCreate(['staff_profile_id' => $staff->id, 'kind' => 'working', 'day_of_week' => $day, 'sequence' => 1], [
                'business_id' => $business->id, 'location_id' => $location->id, 'starts_at' => '09:00', 'ends_at' => '17:30',
            ]);
        }
        OnboardingSession::query()->updateOrCreate(['business_id' => $business->id], [
            'current_step' => 'publish', 'completed_steps' => ['business_details', 'hours', 'services', 'staff', 'staff_availability', 'booking_rules', 'preview', 'publish'],
            'started_at' => now()->subMinutes(24), 'last_saved_at' => now(), 'previewed_at' => now()->subMinutes(2), 'published_at' => now(),
        ]);
    }
}
