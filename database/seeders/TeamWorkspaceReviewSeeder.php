<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentSegment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Isolated synthetic workforce. No outbound invitations or provider calls. */
class TeamWorkspaceReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Local review only.');
        }
        $owner = User::query()->where('email', 'owner@pine-palm.example.test')->firstOrFail();
        $business = Business::query()->firstOrCreate(['slug' => 'team-workspace-review'], ['name' => 'Atelier Studio · Team review', 'status' => 'active', 'country_code' => 'IN', 'locale' => 'en-IN', 'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata']);
        app(BusinessAccessBootstrapper::class)->bootstrap($business);
        $membership = Membership::query()->firstOrCreate(['business_id' => $business->id, 'user_id' => $owner->id], ['status' => 'active', 'joined_at' => now()]);
        app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $owner, 'Isolated workforce review.');
        BusinessSubscription::query()->firstOrCreate(['business_id' => $business->id], ['billing_plan_id' => BillingPlan::query()->where('code', 'pro')->firstOrFail()->id, 'provider' => 'stripe', 'status' => 'trialing', 'restriction_level' => 'none', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14)]);
        $location = Location::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Market Street'], ['time_zone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true]);
        $other = Location::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Riverside · Beauty & wellness studio'], ['time_zone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true]);
        $membership->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id], $other->id => ['business_id' => $business->id]]);
        foreach ([$location, $other] as $branch) {
            for ($day = 1; $day <= 7; $day++) {
                LocationHour::query()->firstOrCreate(['business_id' => $business->id, 'location_id' => $branch->id, 'day_of_week' => $day, 'sequence' => 1], ['opens_at' => '09:00', 'closes_at' => '22:00']);
            }
        }
        $category = ServiceCategory::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Hair & grooming'], ['display_order' => 1, 'is_active' => true]);
        $services = collect([['Signature cut & finish', 40, 120000], ['Colour refresh', 60, 280000], ['Beard shape & hot towel', 25, 60000], ['Conditioning treatment', 20, 45000]])->map(fn ($row) => Service::query()->firstOrCreate(['business_id' => $business->id, 'name' => $row[0]], ['service_category_id' => $category->id, 'kind' => 'service', 'is_active' => true, 'online_visible' => true, 'price_type' => 'fixed', 'duration_minutes' => $row[1], 'price_minor' => $row[2], 'currency_code' => 'INR']));
        foreach ($services as $service) {
            foreach ([$location, $other] as $branch) {
                $service->locations()->syncWithoutDetaching([$branch->id => ['business_id' => $business->id, 'is_eligible' => true]]);
            }
        }
        $names = ['Ava Morgan', 'Iris Chen', 'Noah Brooks', 'Sam Rivera', 'Lena Patel', 'Alexandria Montgomery-Wellington'];
        $staff = collect($names)->map(fn ($name, $i) => StaffProfile::query()->firstOrCreate(['business_id' => $business->id, 'display_name' => $name], ['email' => 'team-'.$i.'@example.test', 'status' => 'active', 'title' => ['Barber', 'Senior colour specialist', 'Stylist', 'Stylist', 'Stylist', 'Senior stylist'][$i], 'online_visible' => true]));
        foreach ($staff as $i => $person) {
            $person->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
            for ($day = 1; $day <= 6; $day++) {
                StaffAvailabilityRule::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $person->id, 'kind' => 'working', 'day_of_week' => $day, 'location_id' => $location->id, 'sequence' => 1], ['starts_at' => $i === 4 ? '20:00' : ($i === 1 ? '10:00' : '09:00'), 'ends_at' => '22:00']);
            }
            foreach ($services->take($i === 0 ? 2 : 4) as $service) {
                StaffServiceAssignment::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $person->id, 'service_id' => $service->id], ['is_active' => true, 'is_qualified' => true, 'online_visible' => true]);
            }
        }
        $now = CarbonImmutable::now('Asia/Kolkata')->startOfMinute();
        StaffAvailabilityRule::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $staff[1]->id, 'kind' => 'break', 'starts_on' => $now->toDateString(), 'ends_on' => $now->toDateString()], ['starts_at' => $now->subMinutes(10)->format('H:i'), 'ends_at' => $now->addMinutes(30)->format('H:i'), 'location_id' => $location->id, 'reason' => 'Synthetic evening break']);
        StaffAvailabilityRule::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $staff[2]->id, 'kind' => 'sick_leave', 'starts_on' => $now->toDateString(), 'ends_on' => $now->toDateString()], ['location_id' => $location->id, 'reason' => 'Synthetic private reason']);
        StaffAvailabilityRule::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $staff[0]->id, 'kind' => 'leave', 'starts_on' => $now->addDays(2)->toDateString(), 'ends_on' => $now->addDays(3)->toDateString()], ['location_id' => $location->id]);
        foreach ([[3, $now->subMinutes(10), 50, 'in_service'], [0, $now->addMinutes(25), 40, 'confirmed'], [5, $now->subHours(2), 60, 'in_service'], [0, $now->addDay()->setTime(10, 0), 40, 'confirmed']] as $i => [$index, $start, $duration, $status]) {
            $a = Appointment::query()->firstOrCreate(['business_id' => $business->id, 'idempotency_key' => 'team-review-'.$i], ['request_hash' => hash('sha256', 'team-review-'.$i), 'location_id' => $location->id, 'client_name' => ['Maya Chen', 'Olivia Bennett', 'Ethan Wilson', 'Jordan Lee'][$i], 'status' => $status, 'source' => 'reception', 'starts_at_utc' => $start->utc(), 'ends_at_utc' => $start->addMinutes($duration)->utc(), 'time_zone' => $location->time_zone, 'local_starts_at' => $start->format('Y-m-d H:i:s P'), 'local_ends_at' => $start->addMinutes($duration)->format('Y-m-d H:i:s P'), 'price_minor' => 120000, 'currency_code' => 'INR', 'version' => 1]);
            $line = AppointmentServiceLine::query()->firstOrCreate(['business_id' => $business->id, 'appointment_id' => $a->id, 'sequence' => 1], ['service_id' => $services[0]->id, 'primary_staff_profile_id' => $staff[$index]->id, 'configuration_snapshot' => ['name' => $services[0]->name, 'durationMinutes' => $duration, 'priceMinor' => 120000], 'price_minor' => 120000, 'currency_code' => 'INR', 'name' => $services[0]->name, 'bookable_minutes' => $duration]);
            AppointmentSegment::query()->firstOrCreate(['business_id' => $business->id, 'appointment_service_line_id' => $line->id, 'sequence' => 1], ['appointment_id' => $a->id, 'staff_profile_id' => $staff[$index]->id, 'kind' => 'active', 'occupies_staff' => true, 'starts_at_utc' => $a->starts_at_utc, 'ends_at_utc' => $a->ends_at_utc, 'time_zone' => $location->time_zone, 'local_starts_at' => $a->local_starts_at, 'local_ends_at' => $a->local_ends_at]);
        }
        $this->command?->info('Team review: '.route('business.team.index', $business));
    }
}
