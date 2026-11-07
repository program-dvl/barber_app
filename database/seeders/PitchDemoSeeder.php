<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\OnboardingSession;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\BusinessConfiguration\Services\BusinessPhotoGallery;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\BusinessConfiguration\Services\StaffPhotoService;
use App\Domain\BusinessConfiguration\Services\StaffWorkforceManager;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientFormTemplate;
use App\Domain\ClientRecords\Models\ClientNote;
use App\Domain\ClientRecords\Models\ClientTag;
use App\Domain\ClientRecords\Services\ClientFormService;
use App\Domain\ClientRecords\Support\ClientIdentityNormalizer;
use App\Domain\ClientReviews\Models\ClientReview;
use App\Domain\Commissions\Services\CommissionLedger;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\InventoryLedger;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Services\CashCloseService;
use App\Domain\MoneyCommerce\Services\CheckoutService;
use App\Domain\MoneyCommerce\Services\ReceiptService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PublicBooking\Services\WaitlistService;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use App\Domain\SchedulingOperations\Services\ScheduleBlockService;
use App\Domain\SchedulingOperations\Services\WalkInQueueService;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Files\TenantFilePath;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Opt-in fictional tenant. Never call from DatabaseSeeder or deployment hooks. */
class PitchDemoSeeder extends Seeder
{
    public const SLUG = 'pitch-demo';

    public const VERSION = 2;

    public const PASSWORD = 'Password@123';

    private array $writtenFiles = [];

    public function run(): void
    {
        if ($existing = Business::query()->where('slug', self::SLUG)->first()) {
            if (! AuditEvent::query()->where('business_id', $existing->id)->where('action', 'demo.pitch.seeded')->exists()) {
                throw new RuntimeException('The pitch-demo slug is already in use without a completed demo seed. Existing data was not changed.');
            }
            DB::transaction(function () use ($existing): void {
                Business::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                $this->restoreDemoPasswords($existing);
                $this->upgradeCanadianDemo($existing);
            });
            $this->summary($existing);

            return;
        }
        $password = self::PASSWORD;
        $emails = collect(['owner', 'manager', 'reception', 'accountant'])->merge(collect(range(1, 10))->map(fn ($n) => 'staff'.$n))->map(fn ($name) => 'pitch.'.$name.'@example.test');
        if (User::query()->whereIn('email', $emails)->exists()) {
            throw new RuntimeException('A pitch login email already exists. No existing account will be reused or modified.');
        }
        foreach (array_merge(range(0, 13), ['market-street', 'riverside']) as $asset) {
            $path = $this->asset((string) $asset);
            if (! is_file($path) || ! getimagesize($path)) {
                throw new RuntimeException('Missing demo image: '.$path.'. Deploy database/seeders/assets/pitch-demo with the seeder.');
            }
        }
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Demo image setup requires the PHP GD extension.');
        }
        $plan = BillingPlan::query()->where('is_trial_default', true)->where('is_active', true)->firstOrFail();
        $dispatch = config('communications.dispatch_operational_events_immediately');
        $mutableClock = Carbon::getTestNow();
        $immutableClock = CarbonImmutable::getTestNow();
        $this->writtenFiles = [];
        config(['communications.dispatch_operational_events_immediately' => false]);
        try {
            $business = DB::transaction(function () use ($password, $plan): Business {
                $business = Business::query()->create([
                    'slug' => self::SLUG, 'booking_slug' => 'atelier-pitch-demo', 'name' => 'Atelier Studio · Demo',
                    'business_type' => 'Hair salon', 'status' => 'active', 'country_code' => 'CA',
                    'locale' => 'en-CA', 'currency_code' => 'CAD', 'time_zone' => 'America/Toronto',
                    'description' => 'A fictional Toronto salon with two neighbourhood studios for guided product demonstrations. All people, visits and payments are sample data.',
                    'week_starts_on' => 1, 'appointment_interval_minutes' => 15, 'tax_posture' => 'inclusive',
                    'phone' => '+1 416 555 0100', 'terms_url' => 'https://example.test/demo-terms', 'privacy_url' => 'https://example.test/demo-privacy', 'cancellation_cutoff_minutes' => 1440,
                    'address' => '120 Demo Queen Street West, Toronto, ON M5H 2N2, Canada', 'email' => 'pitch.owner@example.test',
                    'brand_color' => '#375A4A', 'default_cancellation_policy' => 'Please let us know at least 24 hours before your visit if your plans change.',
                    'configuration_published_at' => now(), 'online_booking_enabled' => true,
                ]);
                app(BusinessAccessBootstrapper::class)->bootstrap($business);
                $locations = collect([
                    ['Queen Street Studio', '120 Demo Queen Street West, Toronto, ON M5H 2N2, Canada', 'market-street'],
                    ['Riverside Studio', '80 Demo Riverside Lane, Toronto, ON M4M 1G9, Canada', 'riverside'],
                ])->map(function ($row, $index) use ($business) {
                    $location = Location::query()->create(['business_id' => $business->id, 'name' => $row[0], 'address' => $row[1], 'email' => 'pitch.branch'.($index + 1).'@example.test', 'status' => 'active', 'is_active' => true, 'time_zone' => $business->time_zone, 'country_code' => 'CA', 'city' => 'Toronto', 'state' => 'ON', 'postal_code' => $index === 0 ? 'M5H 2N2' : 'M4M 1G9', 'address_line_1' => $index === 0 ? '120 Demo Queen Street West' : '80 Demo Riverside Lane', 'phone' => '+1 416 555 01'.($index === 0 ? '00' : '01'), 'is_primary' => $index === 0]);
                    for ($day = 1; $day <= 7; $day++) {
                        LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => $day, 'sequence' => 1, 'opens_at' => '09:00', 'closes_at' => '19:00']);
                    }

                    return $location;
                });
                [$owner, $membership] = $this->login($business, $locations, 'owner', 'Priya Kapoor', StarterRole::Owner, $password, 10);
                $this->login($business, $locations, 'manager', 'Emma Wilson', StarterRole::Manager, $password, 11);
                $this->login($business, $locations, 'reception', 'Arjun Mehta', StarterRole::Receptionist, $password, 12);
                $this->login($business, $locations, 'accountant', 'Daniel Chen', StarterRole::Accountant, $password, 13);
                BusinessSubscription::query()->create([
                    'business_id' => $business->id, 'billing_plan_id' => $plan->id,
                    'provider' => 'manual', 'status' => 'trialing', 'restriction_level' => 'none',
                    'trial_started_at' => now(), 'trial_ends_at' => now()->addYear(),
                    'capacity_snapshot' => $this->demoCapacity(),
                ]);
                BillingInvoice::query()->create(['business_id' => $business->id, 'business_subscription_id' => $business->subscription->id, 'provider' => 'manual', 'provider_invoice_id' => 'pitch-demo-complimentary-'.$business->id, 'number' => 'DEMO-COMPLIMENTARY', 'status' => 'paid', 'currency' => 'CAD', 'subtotal_minor' => 0, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 0, 'amount_due_minor' => 0, 'amount_paid_minor' => 0, 'amount_remaining_minor' => 0, 'issued_at' => now(), 'paid_at' => now(), 'line_items' => [['description' => 'Complimentary demonstration access — no charge', 'amount_minor' => 0]]]);
                CommerceSetting::query()->create(['business_id' => $business->id, 'currency_code' => 'CAD', 'tax_inclusive' => true, 'default_tax_rate_bps' => 1300, 'discount_manager_limit_bps' => 2000]);
                app(CommunicationTemplateService::class)->settings($business->id)->update(['email_enabled' => false, 'sms_enabled' => false, 'automatic_receipt_email' => false, 'automatic_review_email' => false]);

                return app(TenantContext::class)->run($business, $membership, function () use ($business, $locations, $password, $owner, $membership): Business {
                    $staff = $this->team($business, $locations, $password, $membership);
                    $services = $this->catalogue($business, $locations, $staff);
                    $products = $this->inventory($business, $locations, $membership);
                    $clients = $this->clients($business, $staff, $services);
                    $this->visits($business, $locations, $staff, $services, $products, $clients, $membership);
                    $this->extras($business, $locations, $staff, $services, $clients, $membership);
                    $this->media($business, $staff, $owner);
                    $this->completeSetup($business);
                    $readiness = app(ReadinessEvaluator::class)->evaluate($business->fresh());
                    if (! $readiness->publishable) {
                        throw new RuntimeException('Demo booking setup is incomplete: '.implode(' ', array_column($readiness->blockers, 'message')));
                    }
                    // Do not leave any historical event for live queue workers to send.
                    OperationalNotificationEvent::query()->where('business_id', $business->id)->update(['status' => 'ignored']);
                    app(AuditWriter::class)->write('demo.pitch.seeded', $business, $owner, reason: 'Owner-requested fictional sales demonstration. No provider charges or messages.', after: ['version' => self::VERSION, 'locations' => 2, 'staff' => 10]);

                    return $business;
                });
            });
        } catch (Throwable $error) {
            foreach ($this->writtenFiles as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $error;
        } finally {
            Carbon::setTestNow($mutableClock);
            CarbonImmutable::setTestNow($immutableClock);
            config(['communications.dispatch_operational_events_immediately' => $dispatch]);
        }
        $this->summary($business);
    }

    private function demoCapacity(): array
    {
        // Complimentary demo terms only; this does not approve or convert paid CAD rates.
        return ['market' => 'CA', 'currency' => 'CAD', 'billing_interval' => 'monthly', 'locations' => 2, 'staff' => 10, 'total_minor' => 0, 'base_minor' => 0, 'location_minor' => 0, 'staff_minor' => 0, 'included_locations' => 2, 'included_staff' => 10, 'sms_monthly_allowance' => 0, 'features' => config('capacity-billing.features'), 'ready' => false];
    }

    private function completeSetup(Business $business): void
    {
        $session = OnboardingSession::query()->firstOrNew(['business_id' => $business->id]);
        if ($session->guided_completed_at && $session->personalized_at && ($session->generated_data['source'] ?? null) === 'pitch_demo' && ! array_key_exists('version', $session->generated_data ?? [])) {
            return;
        }
        $session->fill([
            'schema_version' => config('business-onboarding.schema_version', 2), 'current_step' => 'publish',
            'completed_steps' => array_values(array_unique([...($session->completed_steps ?? []), 'business_type', 'business_shape', 'location', 'starter_services', 'business_details', 'hours', 'services', 'staff', 'staff_availability', 'booking_rules', 'preview', 'publish'])),
            'answers' => ['business_type' => 'hair_salon', 'country_code' => 'CA', 'currency_code' => 'CAD', 'time_zone' => 'America/Toronto', 'owner_bookable' => false],
            'generated_data' => ['source' => 'pitch_demo', 'demo_version' => self::VERSION, 'locations' => $business->locations()->pluck('public_id')->all(), 'services' => $business->services()->pluck('public_id')->all(), 'staff' => $business->staffProfiles()->pluck('public_id')->all()],
            'started_at' => $session->started_at ?? now()->subHour(), 'last_saved_at' => now(),
            'personalized_at' => $session->personalized_at ?? now(), 'guided_completed_at' => $session->guided_completed_at ?? now(),
            'previewed_at' => $session->previewed_at ?? now(), 'published_at' => $session->published_at ?? now(),
        ])->save();
    }

    private function upgradeCanadianDemo(Business $business): void
    {
        $marker = AuditEvent::query()->where('business_id', $business->id)->where('action', 'demo.pitch.seeded')->firstOrFail();
        if (($marker->after['version'] ?? 1) >= self::VERSION || AuditEvent::query()->where('business_id', $business->id)->where('action', 'demo.pitch.canadian_upgraded')->exists()) {
            $this->completeSetup($business);

            return;
        }
        $dispatch = config('communications.dispatch_operational_events_immediately');
        config(['communications.dispatch_operational_events_immediately' => false]);
        try {
            $actor = $business->memberships()->whereHas('user', fn ($q) => $q->where('email', 'pitch.owner@example.test'))->firstOrFail();
            app(TenantContext::class)->run($business, $actor, function () use ($business, $actor): void {
                $locations = $business->locations()->orderBy('id')->get();
                $staff = collect(range(1, 10))->map(fn ($i) => $business->staffProfiles()->where('email', 'pitch.staff'.$i.'@example.test')->firstOrFail());
                $services = $business->services()->orderBy('id')->get();
                $products = $business->products()->where('sku', 'like', 'AT-DEMO-%')->orderBy('id')->get();
                $clients = $business->clients()->whereIn('email', collect(range(1, 60))->map(fn ($i) => 'atelier.client'.$i.'@example.test'))->orderBy('id')->get();
                if ($locations->count() !== 2 || $services->count() !== 8 || $products->count() !== 8 || $clients->count() !== 60) {
                    throw new RuntimeException('The original demo catalogue has changed. Canadian upgrade stopped without changing records.');
                }
                // Retain snapshots and histories; retire only generated, still-active legacy visits.
                $legacy = Appointment::query()->where('business_id', $business->id)->where('idempotency_key', 'like', 'pitch-visit-%')->where('currency_code', 'INR')->whereIn('status', ['pending_confirmation', 'confirmed', 'late', 'arrived', 'checked_in', 'in_service'])->get();
                foreach ($legacy as $visit) {
                    app(AppointmentLifecycleCommand::class)->transition($visit, 'cancelled_by_shop', 'pitch-ca-retire-'.$visit->id, $visit->version, 'reception', 'demo_seeder', $actor->id, 'Demo refreshed with Canadian locations and CAD appointments. Original booking retained.');
                }
                $business->update(['country_code' => 'CA', 'currency_code' => 'CAD', 'locale' => 'en-CA', 'time_zone' => 'America/Toronto', 'business_type' => 'Hair salon', 'phone' => '+1 416 555 0100', 'address' => '120 Demo Queen Street West, Toronto, ON M5H 2N2, Canada', 'description' => 'A fictional Toronto salon with two neighbourhood studios for guided product demonstrations. All people, visits and payments are sample data.']);
                foreach ($locations as $i => $branch) {
                    $branch->update(['name' => $i === 0 ? 'Queen Street Studio' : 'Riverside Studio', 'address' => $i === 0 ? '120 Demo Queen Street West, Toronto, ON M5H 2N2, Canada' : '80 Demo Riverside Lane, Toronto, ON M4M 1G9, Canada', 'address_line_1' => $i === 0 ? '120 Demo Queen Street West' : '80 Demo Riverside Lane', 'city' => 'Toronto', 'state' => 'ON', 'postal_code' => $i === 0 ? 'M5H 2N2' : 'M4M 1G9', 'country_code' => 'CA', 'time_zone' => 'America/Toronto', 'phone' => $i === 0 ? '+1 416 555 0100' : '+1 416 555 0101', 'is_primary' => $i === 0]);
                }
                CommerceSetting::query()->where('business_id', $business->id)->firstOrFail()->update(['currency_code' => 'CAD', 'default_tax_rate_bps' => 1300]);
                $business->subscription->update(['capacity_snapshot' => $this->demoCapacity()]);
                app(CommunicationTemplateService::class)->settings($business->id)->update(['email_enabled' => false, 'sms_enabled' => false, 'automatic_receipt_email' => false, 'automatic_review_email' => false]);
                BillingInvoice::query()->firstOrCreate(['business_id' => $business->id, 'provider_invoice_id' => 'pitch-ca-complimentary-'.$business->id], ['business_subscription_id' => $business->subscription->id, 'provider' => 'manual', 'number' => 'DEMO-CAD-COMPLIMENTARY', 'status' => 'paid', 'currency' => 'CAD', 'subtotal_minor' => 0, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 0, 'amount_due_minor' => 0, 'amount_paid_minor' => 0, 'amount_remaining_minor' => 0, 'issued_at' => now(), 'paid_at' => now(), 'line_items' => [['description' => 'Complimentary Canadian demonstration access — no charge', 'amount_minor' => 0]]]);
                foreach ($services as $i => $service) {
                    $service->update(['currency_code' => 'CAD', 'price_minor' => [6500, 4000, 2500, 5000, 12000, 6000, 4500, 8500][$i]]);
                    foreach ($staff as $person) {
                        // Specific CAD rules win over retained legacy general rules without rewriting payroll history.
                        app(CommissionLedger::class)->createRule(['business_id' => $business->id, 'staff_profile_id' => $person->id, 'service_id' => $service->id, 'kind' => 'service_percentage', 'rate_bps' => 1500, 'currency_code' => 'CAD', 'effective_from' => now()->subMonthsNoOverflow(3), 'created_by_membership_id' => $actor->id, 'reason' => 'Canadian demo service commission: 15%.']);
                    }
                }
                foreach ($products as $i => $product) {
                    $product->update(['currency_code' => 'CAD', 'sale_price_minor' => 2400 + $i * 300, 'cost_minor' => 1100 + $i * 150, 'tax_rate_bps' => 1300]);
                    if ($i !== 7) {
                        foreach ($locations as $branch) {
                            app(InventoryLedger::class)->receive($product, $branch, 150, $actor, 'Canadian demo replenishment for extended visit history.', 'pitch-ca-stock-'.$branch->id.'-'.$product->id);
                        }
                    }
                }
                foreach ($clients as $i => $client) {
                    $mobile = '+1416555'.str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT);
                    $client->update(['mobile' => $mobile, 'normalized_mobile' => ClientIdentityNormalizer::mobile($mobile)]);
                }
                $gallery = app(BusinessPhotoGallery::class);
                foreach ($gallery->photos($business) as $i => $photo) {
                    $gallery->update($business, $photo->public_id, ($i === 0 ? 'Queen Street Studio' : 'Riverside Studio').', Toronto · fictional demonstration interior', $photo->position, $gallery->revision($business));
                }
                $this->visits($business->fresh(), $locations, $staff, $services, $products, $clients, $actor);
                $this->completeSetup($business);
                if (! app(ReadinessEvaluator::class)->evaluate($business->fresh())->publishable) {
                    throw new RuntimeException('The Canadian demo is not booking-ready. Upgrade rolled back.');
                }
                OperationalNotificationEvent::query()->where('business_id', $business->id)->where('status', 'pending')->update(['status' => 'ignored']);
                app(AuditWriter::class)->write('demo.pitch.canadian_upgraded', $business, $actor->user, reason: 'Owner-requested CAD/Toronto demo, completed introduction and two calendar months either side. Original financial histories retained.', after: ['version' => self::VERSION, 'currency' => 'CAD', 'time_zone' => 'America/Toronto', 'legacy_visits_cancelled' => $legacy->count()]);
            });
        } finally {
            config(['communications.dispatch_operational_events_immediately' => $dispatch]);
        }
    }

    private function login(Business $business, Collection $locations, string $handle, string $name, StarterRole $role, string $password, int $image): array
    {
        $user = new User(['name' => $name, 'email' => 'pitch.'.$handle.'@example.test', 'password' => Hash::make($password)]);
        $user->forceFill(['email_verified_at' => now(), 'trial_is_used' => true])->save();
        $disk = config('jetstream.profile_photo_disk', 'public');
        $path = 'profile-photos/pitch-demo/'.$user->id.'-'.Str::ulid().'.jpg';
        $this->put($disk, $path, file_get_contents($this->asset((string) $image)));
        $user->forceFill(['profile_photo_path' => $path])->save();
        $membership = Membership::query()->create(['business_id' => $business->id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);
        app(MembershipAccessManager::class)->assignStarterRole($membership, $role, $user, 'Fictional pitch demonstration account.');
        $membership->locations()->syncWithPivotValues($locations->pluck('id')->all(), ['business_id' => $business->id]);

        return [$user, $membership];
    }

    private function team(Business $business, Collection $locations, string $password, Membership $ownerMembership): Collection
    {
        $names = ['Ava Morgan', 'Noah Brooks', 'Iris Chen', 'Amelia Rose', 'Jamie Patel', 'Alex Taylor', 'Maya Singh', 'Leo Martin', 'Zara Ali', 'Owen Reed'];

        return collect($names)->map(function ($name, $index) use ($business, $locations, $password, $ownerMembership) {
            $branch = $locations[intdiv($index, 5)];
            [$user, $membership] = $this->login($business, collect([$branch]), 'staff'.($index + 1), $name, StarterRole::BarberStylist, $password, $index);
            $staff = StaffProfile::query()->create(['business_id' => $business->id, 'membership_id' => $membership->id, 'user_id' => $user->id, 'email' => $user->email, 'display_name' => $name, 'title' => ['Senior stylist', 'Barber', 'Colour specialist', 'Stylist', 'Salon professional'][$index % 5], 'biography' => 'A thoughtful consultation, a considered finish and practical aftercare advice. Fictional professional profile for Atelier product demonstrations.', 'status' => 'active', 'online_visible' => true]);
            $staff->locations()->attach($branch->id, ['business_id' => $business->id]);
            for ($day = 1; $day <= 7; $day++) {
                foreach ([['working', '09:00', '19:00'], ['break', '13:00', '14:00']] as [$kind, $start, $end]) {
                    StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'location_id' => $branch->id, 'staff_profile_id' => $staff->id, 'kind' => $kind, 'day_of_week' => $day, 'sequence' => 1, 'starts_at' => $start, 'ends_at' => $end]);
                }
            }
            app(CommissionLedger::class)->createRule(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'kind' => 'service_percentage', 'rate_bps' => 1500, 'currency_code' => 'CAD', 'effective_from' => now()->subMonthsNoOverflow(3), 'created_by_membership_id' => $ownerMembership->id, 'reason' => 'Demo service commission: 15%.']);

            return $staff;
        });
    }

    private function catalogue(Business $business, Collection $locations, Collection $staff): Collection
    {
        $chairs = $locations->mapWithKeys(fn ($branch) => [$branch->id => PhysicalResource::query()->create(['business_id' => $business->id, 'location_id' => $branch->id, 'name' => 'Styling chairs', 'type' => 'barber_chair', 'quantity' => 5, 'is_active' => true])]);
        $categories = collect(['Haircuts & styling', 'Colour & care', 'Barber rituals'])->mapWithKeys(fn ($name) => [$name => ServiceCategory::query()->create(['business_id' => $business->id, 'name' => $name])]);
        $rows = [
            ['Signature cut & finish', 45, 6500, 'Haircuts & styling'], ['Classic barber cut', 30, 4000, 'Barber rituals'],
            ['Beard shape & hot towel', 30, 2500, 'Barber rituals'], ['Blow-dry & style', 45, 5000, 'Haircuts & styling'],
            ['Root colour refresh', 90, 12000, 'Colour & care'], ['Deep conditioning ritual', 45, 6000, 'Colour & care'],
            ['Scalp care & massage', 30, 4500, 'Colour & care'], ['Cut & beard combination', 60, 8500, 'Barber rituals'],
        ];

        return collect($rows)->map(function ($row) use ($business, $locations, $staff, $categories) {
            $service = Service::query()->create(['business_id' => $business->id, 'service_category_id' => $categories[$row[3]]->id, 'name' => $row[0], 'kind' => 'service', 'price_type' => 'fixed', 'duration_minutes' => $row[1], 'price_minor' => $row[2], 'currency_code' => 'CAD', 'tax_category' => 'salon_service', 'tax_inclusive' => true, 'deposit_type' => 'none', 'deposit_value' => 0, 'minimum_notice_minutes' => 60, 'maximum_advance_days' => 90, 'is_active' => true, 'online_visible' => true]);
            $segment = ServiceSegment::query()->create(['business_id' => $business->id, 'service_id' => $service->id, 'sequence' => 1, 'kind' => 'active', 'duration_minutes' => $row[1], 'occupies_staff' => true]);
            foreach ($locations as $branch) {
                $service->locations()->attach($branch->id, ['business_id' => $business->id, 'is_eligible' => true]);
            }
            foreach ($staff as $person) {
                StaffServiceAssignment::query()->create(['business_id' => $business->id, 'staff_profile_id' => $person->id, 'service_id' => $service->id, 'is_qualified' => true, 'is_active' => true, 'online_visible' => true]);
            }

            return $service;
        });
    }

    private function inventory(Business $business, Collection $locations, Membership $actor): Collection
    {
        return collect(['Matte styling clay', 'Colour-care shampoo', 'Repair conditioner', 'Texture spray', 'Beard oil', 'Scalp tonic', 'Curl cream', 'Finishing serum'])->map(function ($name, $index) use ($business, $locations, $actor) {
            $product = InventoryProduct::query()->create(['business_id' => $business->id, 'sku' => 'AT-DEMO-'.($index + 1), 'name' => $name, 'sale_price_minor' => 2400 + $index * 300, 'cost_minor' => 1100 + $index * 150, 'currency_code' => 'CAD', 'tax_rate_bps' => 1300, 'status' => 'active', 'current_stock' => 0, 'low_stock_threshold' => 5]);
            foreach ($locations as $branch) {
                app(InventoryLedger::class)->importOpeningStock($product, $branch, 80, 'pitch-opening-'.$branch->id.'-'.$index);
                app(InventoryLedger::class)->receive($product, $branch, 10, $actor, 'Demo weekly replenishment.', 'pitch-receipt-'.$branch->id.'-'.$index);
            }

            if ($index === 7) {
                foreach ($locations as $branch) {
                    app(InventoryLedger::class)->adjust($product, $branch, -87, $actor, 'Demo stock count: three remaining; reorder example.', 'pitch-count-'.$branch->id);
                }
            }

            return $product;
        });
    }

    private function clients(Business $business, Collection $staff, Collection $services): Collection
    {
        $first = ['Olivia', 'Ethan', 'Sophia', 'Liam', 'Isabella', 'Lucas', 'Charlotte', 'Henry', 'Amara', 'Oliver', 'Anika', 'Rohan', 'Nisha', 'Sam', 'Ira'];
        $last = ['Bennett', 'Shah', 'Thomas', 'Patel', 'Wilson', 'Rao'];
        $tags = collect(['Regular guest', 'Colour care', 'Prefers mornings'])->map(fn ($name) => ClientTag::query()->create(['business_id' => $business->id, 'name' => $name, 'slug' => Str::slug($name)]));

        return collect(range(0, 59))->map(function ($i) use ($business, $staff, $services, $first, $last, $tags) {
            $name = $first[$i % 15].' '.$last[intdiv($i, 15)];
            $client = Client::query()->create(['business_id' => $business->id, 'name' => $name, 'normalized_name' => ClientIdentityNormalizer::name($name), 'email' => 'atelier.client'.($i + 1).'@example.test', 'normalized_email' => 'atelier.client'.($i + 1).'@example.test', 'mobile' => '+1416555'.str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT), 'normalized_mobile' => '1416555'.str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT), 'preferred_staff_profile_id' => $staff[$i % 10]->id, 'preferences' => ['styling' => 'Natural finish; discuss length before starting.'], 'communication_preferences' => ['email' => false, 'sms' => false], 'marketing_status' => 'unsubscribed', 'referral_source' => ['Friend', 'Walk-in', 'Website'][$i % 3], 'status' => 'active', 'created_at' => now()->subDays(45 + $i)]);
            $client->tags()->attach($tags[$i % 3]->id, ['business_id' => $business->id]);
            $client->preferredServices()->attach($services[$i % 8]->id, ['business_id' => $business->id]);
            ClientNote::query()->create(['business_id' => $business->id, 'client_id' => $client->id, 'authored_by_staff_profile_id' => $staff[$i % 10]->id, 'kind' => 'preference', 'visibility' => 'team', 'content' => ['Prefers a quiet appointment and a natural finish.', 'Likes a short consultation before colour services.', 'Please explain the home-care routine after styling.'][$i % 3], 'is_important' => $i % 7 === 0]);

            return $client;
        });
    }

    private function visits(Business $business, Collection $locations, Collection $staff, Collection $services, Collection $products, Collection $clients, Membership $actor): void
    {
        $actualNow = CarbonImmutable::now();
        $today = $actualNow->setTimezone($business->time_zone)->startOfDay();
        $mutable = Carbon::getTestNow();
        $immutable = CarbonImmutable::getTestNow();
        try {
            $firstOffset = -(int) $today->subMonthsNoOverflow(2)->diffInDays($today);
            $lastOffset = (int) $today->diffInDays($today->addMonthsNoOverflow(2));
            for ($offset = $firstOffset; $offset <= $lastOffset; $offset++) {
                foreach ($staff as $index => $person) {
                    // All ten professionals have appointments, with extra afternoon visits on busy days.
                    foreach (($offset % 3 === 0 ? [10, 15] : [10]) as $hour) {
                        $start = $today->addDays($offset)->setTime($hour, ($index % 2) * 15);
                        $key = 'pitch-ca-v2-'.$start->toDateString().'-'.$index.'-'.$hour;
                        if (Appointment::query()->where('business_id', $business->id)->where('idempotency_key', $key)->exists()) {
                            continue;
                        }
                        $branch = $locations[intdiv($index, 5)];
                        $service = $services[abs($offset + $index) % $services->count()];
                        $client = $clients[abs($offset * 7 + $index * 3 + $hour) % $clients->count()];
                        $bookedAt = $start->subDays(5)->min($actualNow)->utc();
                        $this->clock($bookedAt);
                        $visit = app(BookingCommitCommand::class)->commit(new BookingRequest(
                            businessId: $business->id, locationId: $branch->id, startsAtUtc: $start->utc(),
                            lines: [new BookingLineRequest($service->id, $person->id, [], false)],
                            source: ['online', 'phone', 'reception'][$index % 3], asOfUtc: $bookedAt,
                            actorType: 'demo_seeder', actorId: $actor->id, clientName: $client->name,
                            clientMobile: $client->mobile, clientEmail: $client->email,
                            communicationPreferences: ['email' => false, 'sms' => false], clientPublicId: $client->public_id,
                        ), $key);
                        $end = $visit->ends_at_utc;
                        if ($end->greaterThanOrEqualTo($actualNow)) {
                            continue;
                        }
                        $this->clock($end);
                        $target = abs($offset + $index) % 17 === 0 ? 'no_show' : (abs($offset + $index) % 19 === 0 ? 'cancelled_by_client' : 'completed');
                        $path = $target === 'completed' ? ['arrived', 'checked_in', 'in_service', 'completed'] : [$target];
                        foreach ($path as $step => $status) {
                            $occurred = match ($status) {
                                'arrived' => $start->subMinutes(5), 'checked_in' => $start->subMinutes(2), 'in_service' => $start, default => $end
                            };
                            $visit = app(AppointmentLifecycleCommand::class)->transition($visit, $status, 'pitch-status-'.$visit->id.'-'.$step, $visit->version, 'reception', 'demo_seeder', $actor->id, $target !== 'completed' ? 'Fictional demo: client plans changed.' : null, $occurred->utc());
                        }
                        if ($target !== 'completed') {
                            continue;
                        }
                        $extra = $index % 3 === 0 ? [['kind' => 'product', 'source_id' => $products[$index % 8]->id, 'quantity' => 1]] : [];
                        $tips = $index % 4 === 0 ? [['staff_profile_id' => $person->id, 'amount_minor' => 500]] : [];
                        $sale = app(CheckoutService::class)->openForAppointment($visit, $extra, $tips);
                        $partial = $offset === -1 && $index === 2;
                        $amount = $partial ? intdiv($sale->balance_minor, 2) : $sale->balance_minor;
                        $payment = app(CheckoutService::class)->recordTender($sale, ['cash', 'card', 'bank_transfer'][$index % 3], $amount, 'pitch-tender-'.$sale->id, ['synthetic_fixture' => true, 'actor_name' => 'Demo Owner']);
                        if (! $partial) {
                            app(ReceiptService::class)->issue($sale->fresh());
                            if ($offset === -7 && $index === 2) {
                                $this->clock($end->addDay());
                                $line = $sale->lines->first();
                                app(CheckoutService::class)->refund($sale->fresh(), $payment, 1000, 'pitch-refund-'.$sale->id, 'Demo service adjustment.', [['sale_line_id' => $line->id, 'amount_minor' => 1000, 'quantity' => 0, 'disposition' => 'not_applicable']]);
                            }
                        }
                        if ($offset === -3) {
                            ClientReview::query()->create(['business_id' => $business->id, 'appointment_id' => $visit->id, 'client_id' => $client->id, 'rating' => $index % 3 === 0 ? 4 : 5, 'body' => 'Demo feedback: a welcoming visit and a carefully considered finish.', 'author_initials' => Str::substr($client->name, 0, 1).'.', 'public_consent' => false, 'public_consent_evidence' => ['source' => 'synthetic_demo', 'choice' => false], 'request_hash' => hash('sha256', 'pitch-review-'.$visit->id), 'submitted_at' => $end->addHour()]);
                        }
                    }
                }
            }
        } finally {
            Carbon::setTestNow($mutable);
            CarbonImmutable::setTestNow($immutable);
        }
    }

    private function extras(Business $business, Collection $locations, Collection $staff, Collection $services, Collection $clients, Membership $actor): void
    {
        $forms = app(ClientFormService::class);
        $forms->seedStarterTemplates($business->id);
        $template = ClientFormTemplate::query()->where('business_id', $business->id)->where('name', 'Consultation')->firstOrFail();
        foreach ($clients->take(16) as $i => $client) {
            $request = $forms->request($client, $template);
            if ($i < 12) {
                $forms->submit($request, ['what-would-you-like-us-to-know-1' => 'Please keep the finish natural and recommend suitable aftercare.'], null, ['method' => 'synthetic_demo']);
            }
        }
        foreach ($locations as $index => $branch) {
            for ($i = 0; $i < 3; $i++) {
                $client = $clients[50 + $index * 3 + $i];
                app(WalkInQueueService::class)->add($business->id, $branch->id, $services[$i]->id, $client->name, $client->mobile, null, CarbonImmutable::now()->subMinutes(10 + $i * 5), 'Fictional demonstration queue entry.', 'reception', 'demo_seeder', $actor->id, $client->id, $client->email);
                app(WaitlistService::class)->create($business, $branch, $services[$i], null, ['client_name' => $client->name, 'client_mobile' => $client->mobile, 'client_email' => $client->email, 'acceptable_from' => now()->addDay()->toDateString(), 'acceptable_until' => now()->addDays(14)->toDateString(), 'time_from' => '09:00', 'time_until' => '18:00', 'notification_method' => 'email', 'notes' => 'Demo request: an earlier afternoon appointment would be welcome.']);
            }
            $cash = app(CashCloseService::class);
            for ($days = 1; $days <= 7; $days++) {
                $date = CarbonImmutable::now($branch->time_zone)->subDays($days)->startOfDay();
                $expected = $cash->summary($branch, $date, 20000)['expected_cash_minor'];
                $cash->close($branch, $date, 20000, $expected, null, $actor->id);
            }
            $start = CarbonImmutable::now($branch->time_zone)->addDays(2)->setTime(17, 0);
            app(ScheduleBlockService::class)->create($business->id, $branch->id, $staff[$index * 5]->id, 'personal_block', 'Team training', $start->utc(), $start->addMinutes(45)->utc(), 'Demo weekly skills session.', 'demo_seeder');
        }
        foreach ($clients->take(20) as $i => $client) {
            $intent = CommunicationIntent::query()->create(['business_id' => $business->id, 'client_id' => $client->id, 'source_type' => Client::class, 'source_id' => $client->id, 'event_type' => 'appointment.confirmed', 'event_key' => 'pitch-history-'.$i, 'intent_type' => 'booking_confirmation', 'category' => 'transactional', 'legal_basis' => 'synthetic_demo', 'locale' => 'en-CA', 'time_zone' => $business->time_zone, 'scheduled_for_utc' => now()->subDays($i + 1), 'local_scheduled_for' => now()->subDays($i + 1)->setTimezone($business->time_zone)->format('Y-m-d H:i:s'), 'status' => 'suppressed', 'correlation_id' => (string) Str::uuid()]);
            $template = app(CommunicationTemplateService::class)->resolve($business->id, 'booking_confirmation', $i % 2 === 0 ? 'email' : 'sms', 'en-CA');
            CommunicationMessage::query()->create(['communication_template_id' => $template->id, 'business_id' => $business->id, 'communication_intent_id' => $intent->id, 'client_id' => $client->id, 'channel' => $i % 2 === 0 ? 'email' : 'sms', 'recipient' => $i % 2 === 0 ? $client->email : $client->mobile, 'recipient_hash' => hash('sha256', $client->email), 'idempotency_key' => 'pitch-message-'.$business->id.'-'.$i, 'category' => 'transactional', 'legal_basis' => 'synthetic_demo', 'locale' => 'en-CA', 'time_zone' => $business->time_zone, 'template_variables' => [], 'status' => 'suppressed', 'provider' => 'fake', 'suppression_reason' => 'demo_seed_no_delivery', 'attempt_count' => 0, 'max_attempts' => 0, 'queued_at' => now()->subDays($i + 1)]);
        }
    }

    private function media(Business $business, Collection $staff, User $owner): void
    {
        $gallery = app(BusinessPhotoGallery::class);
        foreach (['market-street' => 'Queen Street Studio, Toronto · fictional demonstration interior', 'riverside' => 'Riverside Studio, Toronto · fictional demonstration interior'] as $asset => $caption) {
            $gallery->store($business, new UploadedFile($this->asset($asset), $asset.'.jpg', 'image/jpeg', null, true), $caption, $gallery->revision($business), 'pitch-gallery-'.$asset);
            $photo = $gallery->photos($business)->last();
            $this->writtenFiles[] = ['private', TenantFilePath::private($business, $photo->path)];
        }
        $photos = app(StaffPhotoService::class);
        foreach ($staff as $i => $person) {
            $person->load('locations');
            $photos->save($business, $person, new UploadedFile($this->asset((string) $i), $i.'.jpg', 'image/jpeg', null, true), app(StaffWorkforceManager::class)->profileRevision($person), $owner);
            $this->writtenFiles[] = ['private', TenantFilePath::private($business, $person->fresh()->photo_path)];
        }
        $cover = 'configuration/pitch-demo-cover.jpg';
        $this->put('private', TenantFilePath::private($business, $cover), file_get_contents($this->asset('market-street')));
        $business->update(['cover_image_path' => $cover]);
    }

    private function put(string $disk, string $path, string $contents): void
    {
        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('Could not save a demo image. The demo database transaction was rolled back.');
        }
        $this->writtenFiles[] = [$disk, $path];
    }

    private function asset(string $name): string
    {
        return database_path('seeders/assets/pitch-demo/'.$name.'.jpg');
    }

    private function clock(CarbonImmutable $time): void
    {
        Carbon::setTestNow($time);
        CarbonImmutable::setTestNow($time);
    }

    private function restoreDemoPasswords(Business $business): void
    {
        DB::transaction(function () use ($business): void {
            $handles = collect(['owner', 'manager', 'reception', 'accountant'])->merge(collect(range(1, 10))->map(fn ($n) => 'staff'.$n));
            $users = User::query()->whereIn('email', $handles->map(fn ($handle) => 'pitch.'.$handle.'@example.test'))->lockForUpdate()->get();
            if ($users->count() !== 14 || $users->contains(fn ($user) => ! $user->memberships()->where('business_id', $business->id)->exists() || $user->memberships()->where('business_id', '!=', $business->id)->exists())) {
                throw new RuntimeException('Demo login ownership changed. No passwords were updated.');
            }
            $changed = [];
            foreach ($users as $user) {
                if (! Hash::check(self::PASSWORD, $user->password)) {
                    $user->forceFill(['password' => Hash::make(self::PASSWORD), 'remember_token' => null])->save();
                    $changed[] = $user->id;
                }
            }
            if ($changed !== []) {
                app(AuditWriter::class)->write('demo.pitch.passwords_restored', $business, reason: 'Owner-requested shared demonstration password restored.', after: ['user_ids' => $changed]);
            }
        });
    }

    private function summary(Business $business): void
    {
        $this->command?->info('Atelier demo ready: '.route('business.dashboard', $business));
        $this->command?->info('Owner: pitch.owner@example.test | Manager: pitch.manager@example.test | Reception: pitch.reception@example.test | Accountant: pitch.accountant@example.test');
        $this->command?->info('Team: pitch.staff1@example.test through pitch.staff10@example.test. Password for every demo account: Password@123.');
        $this->command?->info('Existing demo reruns preserve all records and dates, and restore the demo password. Notifications are disabled. Demo access expires '.$business->subscription->trial_ends_at->toDateString().'.');
    }
}
