<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Models\User;

/** @return array{business:Business,location:Location,service:Service,staff:StaffProfile,user:User,membership:Membership} */
function marketplaceBookablePath(bool $deposit = false): array
{
    static $sequence = 0;
    $sequence++;
    $business = Business::factory()->create([
        'name' => 'Pine & Palm Studio', 'booking_slug' => 'pine-palm-public-'.$sequence, 'status' => 'active',
        'configuration_published_at' => now(), 'time_zone' => 'Asia/Kolkata', 'currency_code' => 'INR',
        'default_cancellation_policy' => 'Change or cancel at least 24 hours before your appointment.',
        'terms_url' => 'https://example.test/terms', 'privacy_url' => 'https://example.test/privacy',
        'online_booking_enabled' => true, 'cancellation_cutoff_minutes' => 60, 'waitlist_offer_batch_size' => 2,
    ]);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id, 'name' => 'Indiranagar Studio', 'time_zone' => 'Asia/Kolkata', 'is_active' => true, 'status' => 'active']);
    for ($day = 1; $day <= 7; $day++) {
        LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => $day, 'opens_at' => '09:00', 'closes_at' => '18:00', 'sequence' => 1]);
    }
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $user, 'Public booking test.');
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'membership_id' => $membership->id, 'user_id' => $user->id, 'display_name' => 'Avery', 'email' => 'private-staff@example.test', 'mobile' => '+919000000000', 'online_visible' => true]);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    for ($day = 1; $day <= 7; $day++) {
        StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id, 'kind' => 'working', 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '18:00']);
    }
    $service = Service::query()->create([
        'business_id' => $business->id, 'kind' => 'service', 'name' => 'Signature Cut', 'description' => 'Consultation and cut.',
        'price_type' => 'fixed', 'price_minor' => 3500, 'currency_code' => 'INR', 'duration_minutes' => 60,
        'minimum_notice_minutes' => 0, 'maximum_advance_days' => 60, 'client_eligibility' => 'all',
        'deposit_type' => $deposit ? 'fixed' : 'none', 'deposit_value' => $deposit ? 1000 : 0,
        'is_active' => true, 'online_visible' => true,
    ]);
    $service->locations()->attach($location->id, ['business_id' => $business->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id, 'is_qualified' => true, 'is_active' => true, 'online_visible' => true]);

    return compact('business', 'location', 'service', 'staff', 'user', 'membership');
}
