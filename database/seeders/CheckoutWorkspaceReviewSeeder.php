<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Support\ClientIdentityNormalizer;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\InventoryLedger;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Models\Deposit;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Services\CheckoutService;
use App\Domain\MoneyCommerce\Services\ReceiptService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Models\User;
use Illuminate\Database\Seeder;

/** New isolated local review tenant. No real contacts, provider calls or notification delivery. */
class CheckoutWorkspaceReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Local review only.');
        }
        $owner = User::query()->where('email', 'owner@pine-palm.example.test')->firstOrFail();
        $business = Business::query()->firstOrCreate(['slug' => 'checkout-workspace-review'], ['name' => 'Atelier Studio · Checkout review', 'status' => 'active', 'country_code' => 'IN', 'locale' => 'en-IN', 'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata']);
        app(BusinessAccessBootstrapper::class)->bootstrap($business);
        $membership = Membership::query()->firstOrCreate(['business_id' => $business->id, 'user_id' => $owner->id], ['status' => 'active', 'joined_at' => now()]);
        app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $owner, 'Isolated checkout visual fixture.');
        BusinessSubscription::query()->firstOrCreate(['business_id' => $business->id], ['billing_plan_id' => BillingPlan::query()->where('code', 'pro')->firstOrFail()->id, 'provider' => 'stripe', 'status' => 'trialing', 'restriction_level' => 'none', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14)]);
        $location = Location::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Market Street'], ['time_zone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true]);
        $membership->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
        CommerceSetting::query()->firstOrCreate(['business_id' => $business->id], ['currency_code' => 'INR', 'tax_inclusive' => false, 'default_tax_rate_bps' => 500, 'discount_manager_limit_bps' => 2000]);
        $staff = collect(['Ava Morgan', 'Noah Brooks', 'Iris Chen'])->map(fn ($name) => StaffProfile::query()->firstOrCreate(['business_id' => $business->id, 'display_name' => $name], ['status' => 'active', 'title' => 'Stylist']));
        $services = collect([['Signature cut & finish', 40, 120000], ['Colour refresh', 60, 280000], ['Beard shape & hot towel', 25, 60000], ['Conditioning treatment', 20, 45000]])->map(fn ($row) => Service::query()->firstOrCreate(['business_id' => $business->id, 'name' => $row[0]], ['kind' => 'service', 'is_active' => true, 'price_type' => 'fixed', 'duration_minutes' => $row[1], 'price_minor' => $row[2], 'currency_code' => 'INR']));
        foreach ($staff as $person) {
            $person->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
            foreach ($services as $service) {
                $service->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id, 'is_eligible' => true]]);
                StaffServiceAssignment::query()->firstOrCreate(['business_id' => $business->id, 'staff_profile_id' => $person->id, 'service_id' => $service->id], ['is_active' => true, 'is_qualified' => true]);
            }
        }
        foreach ([['Matte styling clay', 'CLAY-01', 65000, 8], ['Colour-care shampoo', 'SHAM-02', 95000, 5], ['Repair mask · intensive moisture treatment for colour-treated hair', 'MASK-03', 135000, 2], ['Travel texture spray', 'SPRAY-04', 40000, 0]] as $row) {
            $product = InventoryProduct::query()->firstOrCreate(['business_id' => $business->id, 'sku' => $row[1]], ['name' => $row[0], 'barcode' => '890000'.$row[1], 'sale_price_minor' => $row[2], 'tax_rate_bps' => 500, 'cost_minor' => intdiv($row[2], 2), 'currency_code' => 'INR', 'status' => 'active', 'current_stock' => 0]);
            app(InventoryLedger::class)->importOpeningStock($product, $location, $row[3], 'review-'.$row[1]);
        }
        $checkout = app(CheckoutService::class);
        for ($i = 0; $i < 28; $i++) {
            $client = Client::query()->firstOrCreate(['business_id' => $business->id, 'name' => ['Olivia Bennett', 'Ethan Wilson', 'Amelia Rose Montgomery-Wellington', 'Maya Chen', 'Alex Taylor'][$i % 5]], ['status' => 'active', 'normalized_name' => ClientIdentityNormalizer::name(['Olivia Bennett', 'Ethan Wilson', 'Amelia Rose Montgomery-Wellington', 'Maya Chen', 'Alex Taylor'][$i % 5]), 'communication_preferences' => []]);
            $start = now()->subDays($i < 4 ? 0 : $i)->subMinutes(45 + $i * 10);
            $visit = Appointment::query()->firstOrCreate(['business_id' => $business->id, 'idempotency_key' => 'checkout-review-'.$i], ['location_id' => $location->id, 'client_id' => $client->id, 'client_name' => $client->name, 'status' => 'completed', 'source' => $i % 2 === 0 ? 'walk_in' : 'reception', 'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addMinutes(40), 'time_zone' => 'Asia/Kolkata', 'local_starts_at' => $start->copy()->setTimezone('Asia/Kolkata')->toIso8601String(), 'local_ends_at' => $start->copy()->addMinutes(40)->setTimezone('Asia/Kolkata')->toIso8601String(), 'price_minor' => $services[$i % 3]->price_minor, 'currency_code' => 'INR', 'request_hash' => hash('sha256', 'checkout-review-'.$i)]);
            $service = $services[$i % 3];
            AppointmentServiceLine::query()->firstOrCreate(['business_id' => $business->id, 'appointment_id' => $visit->id, 'sequence' => 1], ['service_id' => $service->id, 'primary_staff_profile_id' => $staff[$i % 3]->id, 'name' => $service->name, 'price_minor' => $service->price_minor, 'currency_code' => 'INR', 'bookable_minutes' => $service->duration_minutes, 'configuration_snapshot' => ['taxRateBps' => 500]]);
            if ($i === 0) {
                $payment = PaymentTransaction::query()->firstOrCreate(['business_id' => $business->id, 'idempotency_key' => 'review-deposit'], ['appointment_id' => $visit->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'card', 'amount_minor' => 50000, 'currency_code' => 'INR', 'occurred_at' => now(), 'evidence' => ['synthetic_fixture' => true]]);
                Deposit::query()->firstOrCreate(['business_id' => $business->id, 'payment_transaction_id' => $payment->id], ['appointment_id' => $visit->id, 'client_id' => $client->id, 'original_amount_minor' => 50000, 'currency_code' => 'INR', 'policy_snapshot' => ['synthetic_fixture' => true]]);
            }
            if ($i === 1 || $i >= 4) {
                $sale = Sale::query()->where('appointment_id', $visit->id)->first() ?? $checkout->openForAppointment($visit);
                if ($sale->status === 'open' && $sale->paid_minor === 0) {
                    $checkout->recordTender($sale, $i % 2 === 0 ? 'cash' : 'card', $i === 1 ? 100000 : $sale->balance_minor, 'review-payment-'.$i, ['synthetic_fixture' => true, 'actor_name' => 'Demo Owner']);
                    if ($i !== 1) {
                        app(ReceiptService::class)->issue($sale->fresh());
                    }
                }
            }
        }
        $this->command?->info('Checkout review: http://127.0.0.1:8000/businesses/'.$business->public_id.'/app/checkout-sales');
    }
}
