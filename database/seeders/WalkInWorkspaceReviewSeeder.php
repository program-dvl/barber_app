<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Domain\SchedulingOperations\Models\WalkInHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Isolated synthetic tenant; never changes the existing salon or sends messages. */
class WalkInWorkspaceReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Review data is local only.');
        }
        $owner = User::query()->where('email', 'owner@pine-palm.example.test')->firstOrFail();
        $business = Business::query()->firstOrCreate(['slug' => 'walk-in-workspace-review'], [
            'name' => 'Atelier Studio · UI review', 'status' => 'active', 'country_code' => 'IN', 'locale' => 'en-IN', 'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata', 'appointment_interval_minutes' => 15,
        ]);
        app(BusinessAccessBootstrapper::class)->bootstrap($business);
        $membership = Membership::query()->firstOrCreate(['business_id' => $business->id, 'user_id' => $owner->id], ['status' => 'active', 'joined_at' => now()]);
        app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $owner, 'Isolated local visual review fixture.');
        BusinessSubscription::query()->firstOrCreate(['business_id' => $business->id], ['billing_plan_id' => BillingPlan::query()->where('is_trial_default', true)->firstOrFail()->id, 'provider' => config('billing.provider'), 'status' => 'trialing', 'restriction_level' => 'none', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14)]);
        $location = Location::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Market Street'], ['time_zone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true]);
        $empty = Location::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Quiet Studio'], ['time_zone' => 'America/New_York', 'status' => 'active', 'is_active' => true]);
        $membership->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id], $empty->id => ['business_id' => $business->id]]);
        $date = CarbonImmutable::now('Asia/Kolkata');
        $now = $date->utc();
        $aligned = $date->startOfMinute()->subMinutes($date->minute % 15)->utc();
        foreach ([$location, $empty] as $place) {
            for ($day = 1; $day <= 7; $day++) {
                LocationHour::query()->firstOrCreate(['location_id' => $place->id, 'day_of_week' => $day, 'sequence' => 1], ['business_id' => $business->id, 'opens_at' => '00:00', 'closes_at' => '23:59']);
            }
        }
        $services = collect([['Signature cut', 35], ['Beard shape & hot towel', 20], ['Colour refresh & finishing treatment', 60]])->map(function ($item) use ($business, $location, $empty) {
            $s = Service::query()->firstOrCreate(['business_id' => $business->id, 'name' => $item[0]], ['kind' => 'service', 'is_active' => true, 'duration_minutes' => $item[1], 'processing_minutes' => 0, 'cleanup_minutes' => 0, 'price_minor' => 3500, 'currency_code' => 'INR', 'maximum_advance_days' => 90, 'client_eligibility' => 'all']);
            $s->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id, 'is_eligible' => true], $empty->id => ['business_id' => $business->id, 'is_eligible' => true]]);

            return $s;
        });
        $staff = collect(['Ava Morgan', 'Noah Brooks', 'Iris Chen', 'Levi Singh'])->map(function ($name, $index) use ($business, $location, $services) {
            $s = StaffProfile::query()->firstOrCreate(['business_id' => $business->id, 'display_name' => $name], ['status' => 'active', 'title' => 'Stylist']);
            $s->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
            for ($day = 1; $day <= 7; $day++) {
                StaffAvailabilityRule::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $s->id, 'location_id' => $location->id, 'kind' => 'working', 'day_of_week' => $day], ['starts_at' => '00:00', 'ends_at' => '23:59']);
            }
            foreach ($services as $service) {
                StaffServiceAssignment::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $s->id, 'service_id' => $service->id], ['is_active' => true, 'is_qualified' => $index !== 3, 'online_visible' => true]);
            }

            return $s;
        });
        StaffAvailabilityRule::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $staff[2]->id, 'kind' => 'break', 'starts_on' => $date->toDateString(), 'ends_on' => $date->toDateString()], ['starts_at' => $date->subMinutes(5)->format('H:i'), 'ends_at' => $date->addMinutes(25)->format('H:i'), 'reason' => 'Private fixture reason']);
        $future = app(BookingCommitCommand::class)->commit(new BookingRequest($business->id, $location->id, $aligned->addMinutes(30), [new BookingLineRequest($services[0]->id, $staff[1]->id, [], false)], 'walk_in', 'existing', $now, null, 'seeder', null, 'Scheduled client', '+919000000101'), 'queue-review-booking-'.$date->toDateString());
        $live = app(BookingCommitCommand::class)->commit(new BookingRequest($business->id, $location->id, $aligned, [new BookingLineRequest($services[0]->id, $staff[0]->id, [], false)], 'walk_in', 'existing', $now, null, 'seeder', null, 'Riley Cooper', '+919000000102'), 'queue-review-live-'.$date->toDateString());
        $live->update(['status' => 'in_service', 'service_started_at' => $now->subMinutes(10)]);
        WalkInEntry::query()->firstOrCreate(['business_id' => $business->id, 'client_name' => 'Riley Cooper'], ['location_id' => $location->id, 'service_id' => $services[0]->id, 'assigned_staff_profile_id' => $staff[0]->id, 'appointment_id' => $live->id, 'status' => 'in_service', 'queue_position' => 1, 'arrived_at' => $now->subMinutes(25), 'service_started_at' => $now->subMinutes(10), 'actual_wait_minutes' => 15, 'client_mobile' => '+919000000102']);
        $names = ['Alex Taylor', 'Sofia Martinez', 'Jamie Patel', 'Noah Williams', 'Amelia Rose Montgomery-Wellington', 'Maya Chen', 'Ethan Wilson', 'Zoe Parker', 'Oliver Reed', 'Isla Thompson', 'Lucas Bennett', 'Harper James'];
        foreach ($names as $index => $name) {
            $client = Client::query()->firstOrCreate(['business_id' => $business->id, 'name' => $name], ['normalized_name' => strtolower(str_replace(' ', '', $name)), 'mobile' => '+91900000'.str_pad((string) (200 + $index), 4, '0', STR_PAD_LEFT), 'status' => 'active']);
            $entry = WalkInEntry::query()->firstOrCreate(['business_id' => $business->id, 'client_name' => $name], ['location_id' => $location->id, 'service_id' => $services[$index % 3]->id, 'client_id' => $client->id, 'client_mobile' => $client->mobile, 'queue_position' => $index + 1, 'status' => $index === 2 ? 'notified' : ($index === 1 ? 'assigned' : 'waiting'), 'assigned_staff_profile_id' => $index === 1 ? $staff[1]->id : null, 'preferred_staff_profile_id' => $index === 0 ? $staff[0]->id : null, 'arrived_at' => $now->subMinutes(max(0, 54 - $index * 5)), 'estimated_service_at' => $index < 2 ? $now->subMinutes(10) : $now->addMinutes(15), 'notes' => $index === 0 ? 'Prefers Ava. Please check before assigning another stylist.' : null, 'version' => 1]);
            if (! $entry->history()->exists()) {
                WalkInHistory::query()->create(['business_id' => $business->id, 'walk_in_entry_id' => $entry->id, 'action' => $index === 3 ? 'reordered' : 'created', 'status' => $entry->status, 'source' => 'seeder', 'reason' => $index === 3 ? 'Client returned after stepping away.' : null, 'before' => [], 'after' => [], 'occurred_at' => $entry->arrived_at]);
            }
        }
        $this->command?->info('Review queue: http://127.0.0.1:8000/businesses/'.$business->public_id.'/app/walk-in-queue?location='.$location->public_id);
        $this->command?->info('Empty location: '.$empty->public_id);
    }
}
