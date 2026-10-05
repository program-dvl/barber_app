<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientNote;
use App\Domain\ClientRecords\Services\ClientRecordService;
use App\Domain\ClientRecords\Support\ClientIdentityNormalizer;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
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

/** Isolated local-only synthetic CRM records. No provider, sending or real tenant changes. */
class ClientWorkspaceReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('CRM review data is local only.');
        }
        $owner = User::query()->where('email', 'owner@pine-palm.example.test')->firstOrFail();
        $business = Business::query()->firstOrCreate(['slug' => 'client-workspace-review'], ['name' => 'Atelier Studio · CRM review', 'status' => 'active', 'country_code' => 'IN', 'locale' => 'en-IN', 'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata', 'appointment_interval_minutes' => 15]);
        app(BusinessAccessBootstrapper::class)->bootstrap($business);
        $membership = Membership::query()->firstOrCreate(['business_id' => $business->id, 'user_id' => $owner->id], ['status' => 'active', 'joined_at' => now()]);
        app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $owner, 'Isolated local CRM visual fixture.');
        BusinessSubscription::query()->firstOrCreate(['business_id' => $business->id], ['billing_plan_id' => BillingPlan::query()->where('is_trial_default', true)->firstOrFail()->id, 'provider' => config('billing.provider'), 'status' => 'trialing', 'restriction_level' => 'none', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14)]);
        $location = Location::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Market Street'], ['time_zone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true]);
        $membership->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
        $staff = collect(['Ava Morgan', 'Noah Brooks', 'Iris Chen'])->map(fn ($name) => StaffProfile::query()->firstOrCreate(['business_id' => $business->id, 'display_name' => $name], ['status' => 'active', 'title' => 'Stylist']));
        $services = collect([['Signature cut', 40, 3500], ['Colour refresh', 60, 7500], ['Beard shape & hot towel', 25, 2000]])->map(fn ($item) => Service::query()->firstOrCreate(['business_id' => $business->id, 'name' => $item[0]], ['kind' => 'service', 'is_active' => true, 'duration_minutes' => $item[1], 'price_minor' => $item[2], 'currency_code' => 'INR', 'maximum_advance_days' => 90, 'client_eligibility' => 'all']));
        foreach ($services as $service) {
            $service->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id, 'is_eligible' => true]]);
        }
        for ($day = 1; $day <= 7; $day++) {
            LocationHour::query()->firstOrCreate(['location_id' => $location->id, 'day_of_week' => $day, 'sequence' => 1], ['business_id' => $business->id, 'opens_at' => '00:00', 'closes_at' => '23:59']);
            foreach ($staff as $member) {
                $member->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
                StaffAvailabilityRule::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $member->id, 'location_id' => $location->id, 'kind' => 'working', 'day_of_week' => $day], ['starts_at' => '00:00', 'ends_at' => '23:59']);
                foreach ($services as $service) {
                    StaffServiceAssignment::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $member->id, 'service_id' => $service->id], ['is_active' => true, 'is_qualified' => true, 'online_visible' => true]);
                }
            }
        }
        $names = ['Alex Taylor', 'Alex Taylor', 'Amelia Rose Montgomery-Wellington', 'Ethan Wilson', 'Harper James', 'Isla Thompson', 'Jamie Patel', 'Lucas Bennett', 'Maya Chen', 'Olivia Bennett', 'Oliver Reed', 'Riley Cooper', 'Sofia Martinez', 'Zoe Parker'];
        $clients = collect();
        for ($i = 0; $i < 36; $i++) {
            $name = $names[$i] ?? 'Review Client '.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $mobile = '+1202555'.str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT);
            $client = Client::query()->firstOrCreate(['business_id' => $business->id, 'normalized_mobile' => ClientIdentityNormalizer::mobile($mobile)], ['name' => $name, 'normalized_name' => ClientIdentityNormalizer::name($name), 'mobile' => $mobile, 'email' => $i === 2 ? 'amelia.montgomery-wellington.long.email@example.test' : strtolower(str_replace(' ', '.', $name)).$i.'@example.test', 'normalized_email' => strtolower(str_replace(' ', '.', $name)).$i.'@example.test', 'status' => 'active', 'preferred_staff_profile_id' => $i % 4 === 0 ? null : $staff[$i % 3]->id, 'preferences' => $i === 9 ? ['notes' => 'Prefers a quiet appointment. Keep the finish natural and leave length around the face.'] : [], 'communication_preferences' => ['sms'], 'created_at' => now()->subMonths(14)]);
            $clients->push($client);
            if ($i < 14) {
                $visitCount = $i === 9 ? 24 : ($i % 4 + 1);
                for ($v = 0; $v < $visitCount; $v++) {
                    $this->visit($business, $location, $client, $services[$i === 9 ? 1 : $i % 3], $staff[$i % 3], CarbonImmutable::now($location->time_zone)->subDays(($i === 5 ? 150 : 7) + $v * 28)->setTime(11, 0), 'completed', 'history-'.$i.'-'.$v, true);
                }
                if ($i % 3 !== 0 || $i === 9) {
                    $this->visit($business, $location, $client, $services[$i === 9 ? 1 : $i % 3], $staff[$i % 3], CarbonImmutable::now($location->time_zone)->addDays(1 + $i % 5)->setTime(10 + $i % 7, 0), 'confirmed', 'upcoming-'.$i, false);
                }
            }
        }
        $olivia = $clients[9];
        app(ClientRecordService::class)->syncPreferences($olivia, ['Colour client'], [$services[1]->id]);
        ClientNote::query()->firstOrCreate(['business_id' => $business->id, 'client_id' => $olivia->id, 'content' => 'Confirm the colour formula before starting. Prefers a soft, natural finish.'], ['kind' => 'preference', 'visibility' => 'standard', 'is_important' => true, 'authored_by_staff_profile_id' => $staff[0]->id]);
        ClientNote::query()->firstOrCreate(['business_id' => $business->id, 'client_id' => $olivia->id, 'content' => "Loved the last colour refresh.\nRebook with Ava when possible; confirm before changing staff."], ['kind' => 'general', 'visibility' => 'standard', 'is_important' => false, 'authored_by_staff_profile_id' => $staff[1]->id]);
        $unpaid = $this->visit($business, $location, $olivia, $services[0], $staff[0], CarbonImmutable::now($location->time_zone)->subDays(2)->setTime(11, 0), 'completed', 'unpaid-olivia', false);
        Sale::query()->firstOrCreate(['business_id' => $business->id, 'appointment_id' => $unpaid->id], ['location_id' => $location->id, 'client_id' => $olivia->id, 'status' => 'open', 'currency_code' => 'INR', 'subtotal_minor' => 3500, 'total_minor' => 3500, 'paid_minor' => 1500, 'balance_minor' => 2000, 'calculation_snapshot' => ['synthetic_fixture' => true]]);
        $this->command?->info('CRM review: http://127.0.0.1:8000/businesses/'.$business->public_id.'/app/clients');
        $this->command?->info('Olivia: '.$olivia->public_id);
    }

    private function visit($business, $location, $client, $service, $staff, CarbonImmutable $start, string $status, string $key, bool $paid): Appointment
    {
        $end = $start->addMinutes($service->duration_minutes);
        $visit = Appointment::query()->firstOrCreate(['business_id' => $business->id, 'idempotency_key' => 'crm-review-'.$key], ['location_id' => $location->id, 'client_id' => $client->id, 'status' => $status, 'source' => str_contains($key, '-2') ? 'walk_in' : 'reception', 'client_name' => $client->name, 'client_mobile' => $client->mobile, 'client_email' => $client->email, 'starts_at_utc' => $start->utc(), 'ends_at_utc' => $end->utc(), 'time_zone' => $location->time_zone, 'local_starts_at' => $start->format('Y-m-d H:i:s P'), 'local_ends_at' => $end->format('Y-m-d H:i:s P'), 'price_minor' => $service->price_minor, 'currency_code' => 'INR', 'request_hash' => hash('sha256', $key)]);
        $line = AppointmentServiceLine::query()->firstOrCreate(['business_id' => $business->id, 'appointment_id' => $visit->id, 'sequence' => 1], ['service_id' => $service->id, 'primary_staff_profile_id' => $staff->id, 'name' => $service->name, 'price_minor' => $service->price_minor, 'currency_code' => 'INR', 'bookable_minutes' => $service->duration_minutes, 'configuration_snapshot' => ['synthetic_fixture' => true]]);
        AppointmentSegment::query()->firstOrCreate(['business_id' => $business->id, 'appointment_id' => $visit->id, 'sequence' => 1], ['appointment_service_line_id' => $line->id, 'staff_profile_id' => $staff->id, 'kind' => 'service', 'starts_at_utc' => $visit->starts_at_utc, 'ends_at_utc' => $visit->ends_at_utc, 'time_zone' => $visit->time_zone, 'local_starts_at' => $visit->local_starts_at, 'local_ends_at' => $visit->local_ends_at, 'occupies_staff' => true]);
        if ($paid) {
            $sale = Sale::query()->firstOrCreate(['business_id' => $business->id, 'appointment_id' => $visit->id], ['location_id' => $location->id, 'client_id' => $client->id, 'status' => 'completed', 'currency_code' => 'INR', 'total_minor' => $service->price_minor, 'paid_minor' => $service->price_minor, 'balance_minor' => 0, 'completed_at' => $visit->ends_at_utc, 'calculation_snapshot' => ['synthetic_fixture' => true]]);
            PaymentTransaction::query()->firstOrCreate(['business_id' => $business->id, 'idempotency_key' => 'crm-review-payment-'.$key], ['sale_id' => $sale->id, 'appointment_id' => $visit->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'cash', 'amount_minor' => $service->price_minor, 'currency_code' => 'INR', 'occurred_at' => $visit->ends_at_utc, 'evidence' => ['synthetic_fixture' => true]]);
        }

        return $visit;
    }
}
