<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\Commissions\Services\CommissionLedger;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleLine;
use App\Domain\MoneyCommerce\Services\CheckoutService;
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
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Models\User;
use App\Support\Money\MoneyCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Entirely synthetic. Invoke only in an isolated local/test database. */
class ReportsWorkspaceReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Local review only.');
        }
        if (Business::query()->where('slug', 'reports-workspace-review')->exists()) {
            return;
        }
        $business = Business::query()->create(['slug' => 'reports-workspace-review', 'name' => 'Atelier Studio · Analytics review', 'status' => 'active', 'country_code' => 'IN', 'locale' => 'en-IN', 'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata']);
        app(BusinessAccessBootstrapper::class)->bootstrap($business);
        $user = User::query()->create(['name' => 'Reports Review Owner', 'email' => 'reports.owner@example.test', 'password' => Hash::make('ReportsReview-LocalOnly-2026!'), 'email_verified_at' => now()]);
        $membership = Membership::query()->create(['business_id' => $business->id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);
        app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $user, 'Isolated analytics fixture.');
        BusinessSubscription::query()->create(['business_id' => $business->id, 'billing_plan_id' => BillingPlan::query()->where('code', 'pro')->firstOrFail()->id, 'provider' => 'stripe', 'status' => 'trialing', 'restriction_level' => 'none', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14)]);
        $locations = collect(['Market Street', 'Riverside Studio'])->map(fn ($name) => Location::query()->create(['business_id' => $business->id, 'name' => $name, 'status' => 'active', 'is_active' => true, 'time_zone' => 'Asia/Kolkata']));
        foreach ($locations as $location) {
            $membership->locations()->attach($location->id, ['business_id' => $business->id]);
        }
        $staff = collect(['Ava Morgan', 'Noah Brooks', 'Iris Chen', 'Amelia Rose Montgomery-Wellington', 'Jamie Patel', 'Alex Taylor'])->map(fn ($name) => StaffProfile::query()->create(['business_id' => $business->id, 'display_name' => $name, 'status' => 'active', 'title' => 'Stylist']));
        foreach ($staff as $index => $person) {
            $branch = $locations[$index % 2];
            $person->locations()->attach($branch->id, ['business_id' => $business->id]);
            for ($day = 1; $day <= 7; $day++) {
                StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'location_id' => $branch->id, 'staff_profile_id' => $person->id, 'kind' => 'working', 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '18:00']);
                StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'location_id' => $branch->id, 'staff_profile_id' => $person->id, 'kind' => 'break', 'day_of_week' => $day, 'starts_at' => '13:00', 'ends_at' => '14:00']);
            }app(CommissionLedger::class)->createRule(['business_id' => $business->id, 'staff_profile_id' => $person->id, 'kind' => 'service_percentage', 'rate_bps' => 1500, 'currency_code' => 'INR', 'effective_from' => now()->subYear(), 'created_by_membership_id' => $membership->id, 'reason' => 'Synthetic retained rule.']);
        }
        foreach ($locations as $location) {
            for ($day = 1; $day <= 7; $day++) {
                LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => $day, 'opens_at' => '09:00', 'closes_at' => '18:00']);
            }
        }
        $services = collect([['Signature cut & finish', 40, 120000], ['Colour refresh', 60, 280000], ['Beard shape & hot towel', 25, 60000], ['Conditioning ritual', 30, 90000], ['Colour correction · consultation and intensive restorative treatment', 90, 480000]])->map(fn ($item) => Service::query()->create(['business_id' => $business->id, 'name' => $item[0], 'kind' => 'service', 'duration_minutes' => $item[1], 'price_minor' => $item[2], 'currency_code' => 'INR', 'is_active' => true]));
        $clients = collect(range(1, 54))->map(fn ($i) => Client::query()->create(['business_id' => $business->id, 'name' => 'Synthetic Client '.$i, 'normalized_name' => 'syntheticclient'.$i, 'status' => 'active', 'communication_preferences' => []]));
        $today = CarbonImmutable::now('Asia/Kolkata')->startOfDay();
        try {
            for ($offset = 75; $offset >= 0; $offset--) {
                $day = $today->subDays($offset);
                $volume = $day->dayOfWeekIso === 6 ? 12 : ($day->dayOfWeekIso === 1 ? 4 : 8);
                for ($i = 0; $i < $volume; $i++) {
                    $person = $staff[$i % 6];
                    $location = $locations[$i % 6 % 2];
                    $service = $services[($offset + $i) % 5];
                    $start = $day->setTime(9 + intdiv($i, 2), ($i % 2) * 30);
                    $until = $start->addMinutes($service->duration_minutes);
                    $client = $clients[($offset + $i * 3) % 54];
                    CarbonImmutable::setTestNow($until->utc());
                    $status = ($offset + $i) % 19 === 0 ? 'no_show' : (($offset + $i) % 17 === 0 ? 'cancelled_by_client' : 'completed');
                    $visit = Appointment::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'client_id' => $client->id, 'client_name' => $client->name, 'status' => $status, 'source' => $i % 4 === 0 ? 'walk_in' : ($i % 3 === 0 ? 'online' : 'reception'), 'idempotency_key' => 'analytics-'.$offset.'-'.$i, 'request_hash' => hash('sha256', 'analytics-'.$offset.'-'.$i), 'starts_at_utc' => $start->utc(), 'ends_at_utc' => $until->utc(), 'time_zone' => 'Asia/Kolkata', 'local_starts_at' => $start->toIso8601String(), 'local_ends_at' => $until->toIso8601String(), 'price_minor' => $service->price_minor, 'currency_code' => 'INR']);
                    $line = AppointmentServiceLine::query()->create(['business_id' => $business->id, 'appointment_id' => $visit->id, 'service_id' => $service->id, 'primary_staff_profile_id' => $person->id, 'sequence' => 1, 'name' => $service->name, 'price_minor' => $service->price_minor, 'currency_code' => 'INR', 'bookable_minutes' => $service->duration_minutes, 'configuration_snapshot' => []]);
                    AppointmentSegment::query()->create(['business_id' => $business->id, 'appointment_id' => $visit->id, 'appointment_service_line_id' => $line->id, 'staff_profile_id' => $person->id, 'sequence' => 1, 'kind' => 'service', 'starts_at_utc' => $start->utc(), 'ends_at_utc' => $until->utc(), 'time_zone' => 'Asia/Kolkata', 'local_starts_at' => $start->toIso8601String(), 'local_ends_at' => $until->toIso8601String(), 'occupies_staff' => true]);
                    if ($i % 4 === 0) {
                        WalkInEntry::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'service_id' => $service->id, 'appointment_id' => $visit->id, 'assigned_staff_profile_id' => $person->id, 'client_name' => $client->name, 'client_mobile' => '+12025550100', 'status' => 'completed', 'queue_position' => $i + 1, 'arrived_at' => $start->subMinutes(5 + $i * 3)->utc(), 'service_started_at' => $start->utc(), 'actual_wait_minutes' => 5 + $i * 3]);
                    }
                    if ($status !== 'completed') {
                        continue;
                    }
                    $currency = $offset % 23 === 0 && $i === 2 ? 'USD' : 'INR';
                    $price = $currency === 'USD' ? 8500 : $service->price_minor;
                    $discount = ($offset + $i) % 7 === 0 ? intdiv($price, 10) : 0;
                    $tip = $i % 4 === 0 ? intdiv($price, 20) : 0;
                    $items = [['kind' => 'service', 'description' => $service->name, 'quantity' => 1, 'unit_price_minor' => $price, 'discount_minor' => $discount, 'tax_rate_bps' => 500]];
                    $snapshot = app(MoneyCalculator::class)->calculate($items, $currency, $offset % 2 === 0, $tip);
                    $partial = $offset < 5 && $i === 1;
                    $sale = Sale::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'appointment_id' => $visit->id, 'client_id' => $client->id, 'status' => $partial ? 'open' : 'completed', 'currency_code' => $currency, 'subtotal_minor' => $snapshot['subtotal_minor'], 'discount_minor' => $snapshot['discount_minor'], 'tax_minor' => $snapshot['tax_minor'], 'tip_minor' => $tip, 'total_minor' => $snapshot['total_minor'], 'paid_minor' => $partial ? intdiv($snapshot['total_minor'], 2) : $snapshot['total_minor'], 'balance_minor' => $partial ? $snapshot['total_minor'] - intdiv($snapshot['total_minor'], 2) : 0, 'calculation_snapshot' => $snapshot, 'completed_at' => $partial ? null : $until->utc(), 'created_at' => $until->utc()]);
                    $saleLine = SaleLine::query()->create(['business_id' => $business->id, 'sale_id' => $sale->id, 'service_id' => $service->id, 'staff_profile_id' => $person->id, 'sequence' => 1, 'source_snapshot' => [], ...$items[0]]);
                    $payment = PaymentTransaction::query()->create(['business_id' => $business->id, 'sale_id' => $sale->id, 'appointment_id' => $visit->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => ['cash', 'card', 'upi', 'bank_transfer'][$i % 4], 'amount_minor' => $sale->paid_minor, 'currency_code' => $currency, 'idempotency_key' => 'analytics-payment-'.$sale->id, 'occurred_at' => $until->utc()]);
                    if ($tip) {
                        DB::table('sale_tip_allocations')->insert(['business_id' => $business->id, 'sale_id' => $sale->id, 'staff_profile_id' => $person->id, 'amount_minor' => $tip, 'created_at' => $until->utc(), 'updated_at' => $until->utc()]);
                    }
                    if (! $partial) {
                        app(CommissionLedger::class)->earnForCompletedSale($sale);
                    }
                    if (! $partial && ($offset + $i) % 31 === 0) {
                        CarbonImmutable::setTestNow($until->addDays(3)->min($today->endOfDay())->utc());
                        app(CheckoutService::class)->refund($sale, $payment, 10000, 'analytics-return-'.$sale->id, 'Synthetic service adjustment.', [['sale_line_id' => $saleLine->id, 'amount_minor' => 10000, 'quantity' => 0, 'disposition' => 'not_applicable']]);
                    }
                }
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
        $services->last()->update(['name' => 'Colour correction · new catalogue name', 'is_active' => false]);
        $this->command?->info('Reports review: http://127.0.0.1:8140/businesses/'.$business->public_id.'/app/reports');
    }
}
