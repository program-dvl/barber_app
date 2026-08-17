<?php

use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Contracts\AppointmentImpactSource;
use App\Domain\BusinessConfiguration\Contracts\AvailabilityConfiguration;
use App\Domain\BusinessConfiguration\Jobs\ProcessConfigurationImport;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\LocationScheduleException;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceResourceRequirement;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\BusinessConfiguration\Services\CapacityRequirementResolver;
use App\Domain\BusinessConfiguration\Services\ConfigurationChangePreviewer;
use App\Domain\BusinessConfiguration\Services\ConfigurationImportService;
use App\Domain\BusinessConfiguration\Services\EffectiveServiceResolver;
use App\Domain\BusinessConfiguration\Services\LocalHoursResolver;
use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\BusinessConfiguration\Services\ResourceMaintenanceManager;
use App\Domain\BusinessConfiguration\Services\StaffScheduleValidator;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\GoodHoursDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('reports explicit readiness blockers and verifies the under-30-minute demo definition', function () {
    $empty = Business::factory()->create();
    $notReady = app(ReadinessEvaluator::class)->evaluate($empty);

    expect($notReady->publishable)->toBeFalse()
        ->and($notReady->blockers)->not->toBeEmpty()
        ->and($notReady->toArray())->not->toHaveKey('percentage');

    $this->seed(GoodHoursDemoSeeder::class);
    $demo = Business::query()->where('booking_slug', 'pine-palm-demo')->firstOrFail();
    $ready = app(ReadinessEvaluator::class)->evaluate($demo);

    expect($ready->publishable)->toBeTrue()
        ->and($ready->blockers)->toBe([])
        ->and($demo->onboardingSession->started_at->diffInMinutes($demo->onboardingSession->published_at))->toBeLessThanOrEqual(30);
});

it('enforces configuration role permissions and tenant isolation at the HTTP boundary', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    [$receptionist, $receptionBusiness] = createTenantMembership(StarterRole::Receptionist);

    $this->actingAs($owner)->get(route('business.configuration.show', $business))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Configuration/Onboarding')->has('readiness.blockers'));

    $this->actingAs($receptionist)->get(route('business.configuration.show', $receptionBusiness))->assertForbidden();
    $this->actingAs($owner)->get(route('business.configuration.show', $receptionBusiness))->assertForbidden();
});

it('creates a complete first bookable path through the guided interface', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $business->update([
        'booking_slug' => 'guided-shop', 'business_type' => 'Salon', 'country_code' => 'IN', 'locale' => 'en-IN',
        'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata', 'week_starts_on' => 1, 'appointment_interval_minutes' => 15,
        'tax_posture' => 'inclusive', 'phone' => '+919000000000', 'email' => 'shop@example.test', 'address' => 'Demo Road',
        'default_cancellation_policy' => '24 hours notice.', 'terms_url' => 'https://example.test/terms', 'privacy_url' => 'https://example.test/privacy',
    ]);
    BusinessSubscription::query()->create([
        'business_id' => $business->id, 'billing_plan_id' => BillingPlan::query()->where('is_trial_default', true)->firstOrFail()->id,
        'status' => SubscriptionStatus::Trialing, 'restriction_level' => RestrictionLevel::None,
        'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14),
    ]);

    $this->actingAs($owner)->post(route('business.configuration.first-bookable-path.store', $business), [
        'location_name' => 'Main studio', 'location_address' => 'Demo Road', 'time_zone' => 'Asia/Kolkata',
        'working_days' => [1, 2, 3, 4, 5, 6], 'opens_at' => '09:00', 'closes_at' => '18:00',
        'staff_name' => 'Mira', 'staff_email' => 'mira@example.test', 'staff_title' => 'Stylist',
        'service_name' => 'Cut and finish', 'category_name' => 'Cuts', 'price_minor' => 90000,
        'duration_minutes' => 40, 'processing_minutes' => 10, 'cleanup_minutes' => 5,
        'tax_category' => 'salon_service', 'resource_name' => 'Styling chairs', 'resource_type' => 'chair',
        'resource_quantity' => 2, 'required_quantity' => 1,
    ])->assertRedirect();
    app(OnboardingManager::class)->markPreviewed($business->fresh());

    expect(app(ReadinessEvaluator::class)->evaluate($business->fresh())->publishable)->toBeTrue()
        ->and($business->locations()->count())->toBe(1)
        ->and($business->staffProfiles()->count())->toBe(1)
        ->and($business->services()->count())->toBe(1)
        ->and($business->physicalResources()->firstOrFail()->quantity)->toBe(2);
});

it('keeps booking slugs unique and redirects every prior alias to the current slug', function () {
    $first = Business::factory()->create(['booking_slug' => 'first-shop', 'configuration_published_at' => now()]);
    $second = Business::factory()->create(['booking_slug' => 'second-shop']);
    $manager = app(BookingSlugManager::class);

    $manager->change($first, 'First Shop New');
    expect($first->fresh()->booking_slug)->toBe('first-shop-new')
        ->and($first->bookingSlugAliases()->where('slug', 'first-shop')->exists())->toBeTrue()
        ->and($manager->resolve('first-shop')->is($first))->toBeTrue();

    expect(fn () => $manager->change($second, 'first-shop'))
        ->toThrow(ValidationException::class);

    $this->get(route('booking.business', 'first-shop'))->assertRedirect(route('booking.business', 'first-shop-new'));
});

it('resolves local normal and special hours with closure precedence across a DST boundary', function () {
    $business = Business::factory()->create();
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'America/New_York']);
    LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00', 'sequence' => 1]);
    $resolver = app(LocalHoursResolver::class);
    $date = CarbonImmutable::parse('2026-03-09 12:00:00', 'America/New_York');

    expect($resolver->windows($location, $date))->toBe([['opens_at' => '09:00', 'closes_at' => '17:00', 'source' => 'normal_hours']]);

    LocationScheduleException::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'kind' => 'special_hours', 'starts_on' => '2026-03-09', 'ends_on' => '2026-03-09', 'opens_at' => '11:00', 'closes_at' => '15:00', 'name' => 'Late opening']);
    expect($resolver->windows($location, $date))->toBe([['opens_at' => '11:00', 'closes_at' => '15:00', 'source' => 'special_hours']]);

    LocationScheduleException::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'kind' => 'temporary_closure', 'starts_on' => '2026-03-09', 'ends_on' => '2026-03-09', 'name' => 'Repairs']);
    expect($resolver->windows($location, $date))->toBe([]);
});

it('stores resource maintenance as UTC plus original local intent', function () {
    $business = Business::factory()->create();
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'America/New_York']);
    $resource = PhysicalResource::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'type' => 'chair', 'name' => 'Chair 1', 'quantity' => 1]);
    LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => 7, 'opens_at' => '09:00', 'closes_at' => '17:00', 'sequence' => 1]);

    $block = app(ResourceMaintenanceManager::class)->block($resource, '2026-03-08 03:30:00', '2026-03-08 04:30:00', 'Annual service');

    expect($block->starts_at_utc->format('Y-m-d H:i:s'))->toBe('2026-03-08 07:30:00')
        ->and($block->local_starts_at)->toBe('2026-03-08 03:30:00')
        ->and($block->time_zone)->toBe('America/New_York')
        ->and(app(AvailabilityConfiguration::class)->resourceWindows($resource, CarbonImmutable::parse('2026-03-08', 'America/New_York'))[0]['source'])->toBe('normal_hours')
        ->and(app(AvailabilityConfiguration::class)->resourceMaintenance($resource, CarbonImmutable::parse('2026-03-08T07:00:00Z'), CarbonImmutable::parse('2026-03-08T09:00:00Z')))->toHaveCount(1);
});

it('subtracts recurring breaks and dated leave from staff availability', function () {
    $business = Business::factory()->create();
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'Asia/Kolkata']);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'status' => 'active']);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id, 'kind' => 'working', 'day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id, 'kind' => 'break', 'day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '13:00']);
    $contract = app(AvailabilityConfiguration::class);

    expect($contract->staffWindows($staff, $location, CarbonImmutable::parse('2026-09-07', 'Asia/Kolkata')))->toBe([
        ['opens_at' => '09:00', 'closes_at' => '12:00', 'source' => 'working'],
        ['opens_at' => '13:00', 'closes_at' => '17:00', 'source' => 'working'],
    ]);

    StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'kind' => 'leave', 'starts_on' => '2026-09-07', 'ends_on' => '2026-09-07', 'reason' => 'Leave']);
    expect($contract->staffWindows($staff, $location, CarbonImmutable::parse('2026-09-07', 'Asia/Kolkata')))->toBe([]);
});

it('rejects overlapping and travel-impossible staff schedules while allowing split shifts', function () {
    $validator = app(StaffScheduleValidator::class);
    $validator->validate([
        ['kind' => 'working', 'day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00', 'location_id' => 1],
        ['kind' => 'working', 'day_of_week' => 1, 'starts_at' => '13:00', 'ends_at' => '17:00', 'location_id' => 2],
    ]);

    expect(fn () => $validator->validate([
        ['kind' => 'working', 'day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00', 'location_id' => 1],
        ['kind' => 'working', 'day_of_week' => 1, 'starts_at' => '12:30', 'ends_at' => '17:00', 'location_id' => 2],
    ]))->toThrow(ValidationException::class, 'different locations');
});

it('resolves staff-specific commercial and duration variants and preserves a historical snapshot', function () {
    [$business, $location, $staff, $service] = configuredServicePath();
    StaffServiceAssignment::query()->where('service_id', $service->id)->update([
        'price_minor' => 15000, 'duration_minutes' => 50, 'processing_minutes' => 10, 'cleanup_minutes' => 5,
    ]);
    $resolver = app(EffectiveServiceResolver::class);
    $resolved = $resolver->resolve($service->fresh(), $staff, $location, CarbonImmutable::parse('2026-09-01T10:00:00Z'));
    $snapshot = $resolver->capture($resolved);
    $service->update(['price_minor' => 99999, 'duration_minutes' => 99]);

    expect($resolved->priceMinor)->toBe(15000)
        ->and($resolved->bookableMinutes)->toBe(65)
        ->and($resolved->depositMinor)->toBe(3750)
        ->and($snapshot->fresh()->values['priceMinor'])->toBe(15000)
        ->and($snapshot->fresh()->values['bookableMinutes'])->toBe(65);
});

it('aggregates service and add-on resource quantities without claiming capacity', function () {
    [$business, $location, , $service] = configuredServicePath();
    $resource = PhysicalResource::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'type' => 'basin', 'name' => 'Wash basins', 'quantity' => 2]);
    $addon = Service::query()->create(['business_id' => $business->id, 'kind' => 'addon', 'name' => 'Hair wash', 'price_minor' => 3000, 'currency_code' => 'INR', 'duration_minutes' => 10]);
    ServiceResourceRequirement::query()->create(['business_id' => $business->id, 'service_id' => $service->id, 'physical_resource_id' => $resource->id, 'quantity' => 1]);
    ServiceResourceRequirement::query()->create(['business_id' => $business->id, 'service_id' => $addon->id, 'physical_resource_id' => $resource->id, 'quantity' => 2]);

    $requirements = app(CapacityRequirementResolver::class)->resolve($service, [$addon]);
    expect($requirements)->toHaveCount(1)
        ->and($requirements[0]->quantity)->toBe(3)
        ->and($requirements[0]->availableQuantity)->toBe(2)
        ->and($requirements[0]->satisfiable)->toBeFalse();
});

it('persists exact impacted-appointment previews through the scheduling adapter contract', function () {
    $this->app->bind(AppointmentImpactSource::class, fn () => new class implements AppointmentImpactSource
    {
        public function affectedAppointmentIds(Business $business, Model $subject, string $changeType, array $proposedChange): array
        {
            return ['01DEMOAPPOINTMENT1', '01DEMOAPPOINTMENT2', '01DEMOAPPOINTMENT2'];
        }
    });
    $business = Business::factory()->create();
    $location = Location::factory()->create(['business_id' => $business->id]);
    $preview = app(ConfigurationChangePreviewer::class)->preview($business, $location, 'temporary_closure', ['starts_on' => '2026-09-01']);

    expect($preview->affected_count)->toBe(2)
        ->and($preview->affected_appointment_ids)->toBe(['01DEMOAPPOINTMENT1', '01DEMOAPPOINTMENT2'])
        ->and($preview->status)->toBe('previewed');
});

it('requires a fresh impact preview before changing a published schedule', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $business->update(['configuration_published_at' => now()]);
    $location = Location::factory()->create(['business_id' => $business->id]);
    $windows = [['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00', 'sequence' => 1]];

    $this->actingAs($owner)->putJson(route('business.configuration.locations.hours.update', [$business, $location]), ['windows' => $windows])
        ->assertUnprocessable()->assertJsonValidationErrors('impact_preview_id');

    $previewId = $this->actingAs($owner)->postJson(route('business.configuration.change-previews.store', [$business, 'location', $location->public_id]), [
        'change_type' => 'location_hours', 'proposed_change' => ['windows' => $windows],
    ])->assertOk()->json('public_id');

    $this->actingAs($owner)->putJson(route('business.configuration.locations.hours.update', [$business, $location]), [
        'windows' => $windows, 'impact_preview_id' => $previewId,
    ])->assertRedirect();
    expect($location->hours()->count())->toBe(1);
});

it('previews imports, requires duplicate review, exports errors privately, and replays idempotently', function () {
    Storage::fake('private');
    $business = Business::factory()->create(['currency_code' => 'INR']);
    app(TenantContext::class)->activate($business);
    $service = app(ConfigurationImportService::class);
    $csv = "external,name,email,mobile\nC1,Asha,asha@example.test,+919000000001";
    $mapping = ['external_id' => 'external', 'name' => 'name', 'email' => 'email', 'mobile' => 'mobile'];
    $first = $service->preview($business, 'clients', 'clients-1', 'clients.csv', $csv, $mapping);
    $job = new ProcessConfigurationImport($first->id);
    $service->commit($first);
    $replay = $service->preview($business, 'clients', 'clients-1', 'clients.csv', $csv, $mapping);

    expect($replay->id)->toBe($first->id)
        ->and($first->fresh()->created_rows)->toBe(1)
        ->and(Client::query()->where('business_id', $business->id)->where('normalized_email', 'asha@example.test')->exists())->toBeTrue()
        ->and($job->tenantBusinessId())->toBe($business->id)
        ->and(serialize($job))->toContain('tenantCorrelationId')
        ->and(Storage::disk('private')->exists('businesses/'.$business->id.'/private/'.$first->source_path))->toBeTrue();

    $duplicate = $service->preview($business, 'clients', 'clients-2', 'clients-2.csv', "external,name,email,mobile\nC2,Asha Patel,asha@example.test,+919000000002", $mapping);
    expect($duplicate->duplicate_rows)->toBe(1)
        ->and(fn () => $service->commit($duplicate))->toThrow(ValidationException::class);

    $invalid = $service->preview($business, 'clients', 'clients-3', 'bad.csv', "name,email\n,bad-email", ['name' => 'name', 'email' => 'email']);
    expect($invalid->failed_rows)->toBe(1)
        ->and($invalid->error_export_path)->not->toBeNull()
        ->and(Storage::disk('private')->exists('businesses/'.$business->id.'/private/'.$invalid->error_export_path))->toBeTrue();

    expect(fn () => $service->preview($business, 'clients', 'malformed', 'bad.csv', "name,email\nAsha", ['name' => 'name', 'email' => 'email']))
        ->toThrow(ValidationException::class, 'different number of columns');
    app(TenantContext::class)->clear();
});

it('enforces staff entitlements when an import would increase usage', function () {
    Storage::fake('private');
    $business = Business::factory()->create();
    app(TenantContext::class)->activate($business);
    $service = app(ConfigurationImportService::class);
    $import = $service->preview($business, 'staff', 'staff-1', 'staff.csv', "name,email\nKai,kai@example.test", ['display_name' => 'name', 'email' => 'email']);

    expect(fn () => $service->commit($import))->toThrow(AuthorizationException::class);
    app(TenantContext::class)->clear();
});

it('offers one stable read-only configuration contract to the future booking engine', function () {
    $contract = app(AvailabilityConfiguration::class);
    expect($contract)->toBeInstanceOf(AvailabilityConfiguration::class)
        ->and(method_exists($contract, 'locationWindows'))->toBeTrue()
        ->and(method_exists($contract, 'staffWindows'))->toBeTrue()
        ->and(method_exists($contract, 'resourceWindows'))->toBeTrue()
        ->and(method_exists($contract, 'resourceMaintenance'))->toBeTrue()
        ->and(method_exists($contract, 'resolveService'))->toBeTrue()
        ->and(method_exists($contract, 'requiredCapacity'))->toBeTrue();
});

/** @return array{0:Business,1:Location,2:StaffProfile,3:Service} */
function configuredServicePath(): array
{
    $business = Business::factory()->create(['currency_code' => 'INR', 'tax_posture' => 'inclusive']);
    $location = Location::factory()->create(['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'status' => 'active']);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id, 'kind' => 'working', 'day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    $service = Service::query()->create([
        'business_id' => $business->id, 'kind' => 'service', 'name' => 'Cut', 'price_minor' => 12000,
        'currency_code' => 'INR', 'tax_category' => 'service', 'tax_inclusive' => true, 'duration_minutes' => 40,
        'processing_minutes' => 0, 'cleanup_minutes' => 5, 'deposit_type' => 'percentage', 'deposit_value' => 2500,
        'is_active' => true, 'online_visible' => true,
    ]);
    $service->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id, 'is_qualified' => true, 'is_active' => true, 'online_visible' => true]);

    return [$business, $location, $staff, $service];
}
