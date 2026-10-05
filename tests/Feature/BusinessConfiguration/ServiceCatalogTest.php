<?php

use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\BusinessConfiguration\Services\ConfigurationImportService;
use App\Domain\BusinessConfiguration\Services\EffectiveServiceResolver;
use App\Domain\BusinessConfiguration\Services\ServiceCatalogManager;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Support\AuditEventPresentation;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    CarbonImmutable::setTestNow('2035-10-09 04:30 UTC');
});
afterEach(function () {
    CarbonImmutable::setTestNow();
    app(TenantContext::class)->clear();
});

function catalogData(array $p, array $changes = []): array
{
    $s = $p['service']->fresh(ServiceCatalogManager::RELATIONS);
    $manager = app(ServiceCatalogManager::class);

    return [...$s->only(ServiceCatalogManager::FIELDS), 'category' => $s->category?->name,
        'location_ids' => [$p['location']->public_id], 'staff_ids' => [$p['staff']->public_id],
        'revision' => $manager->revision($s), 'impact_revision' => $manager->impactRevision($manager->upcoming($p['business'])->get($s->id, collect())),
        'command_key' => (string) Str::uuid(), ...$changes];
}

it('projects current versions, add-ons, branch overrides and setup health', function () {
    $p = operationalPath();
    $assignment = $p['service']->staffAssignments()->first();
    $assignment->update(['duration_minutes' => 45, 'price_minor' => 5000]);
    StaffServiceAssignment::query()->create(['business_id' => $p['business']->id, 'service_id' => $p['service']->id, 'staff_profile_id' => $p['staff']->id, 'is_active' => true, 'is_qualified' => true, 'effective_from' => now()->addDays(3), 'duration_minutes' => 90]);
    $addon = Service::query()->create(['business_id' => $p['business']->id, 'name' => 'Conditioning', 'kind' => 'addon', 'price_minor' => 1000, 'currency_code' => 'INR', 'duration_minutes' => 15]);
    $this->actingAs($p['user'])->get(route('business.services.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Services/Index')->has('services', 2)->where('services.1.staff.0.duration_minutes', 45)->where('services.1.dated_configuration', true)->where('services.0.warnings.0', 'No active location')->has('commerce'));
});

it('saves staff and branch overrides through the same resolver and retains booked snapshots', function () {
    $p = operationalPath();
    $appointment = operationalBooking($p, '2035-10-10 10:00', 'catalog-price');
    $snapshot = $appointment->serviceLines()->first()->configuration_snapshot;
    $data = catalogData($p, ['price_minor' => 7000, 'duration_minutes' => 40, 'reason' => 'Reviewed existing appointments',
        'staff_overrides' => [['staff' => $p['staff']->public_id, 'price_minor' => 9000, 'duration_minutes' => 45, 'online_visible' => true]],
        'location_overrides' => [['location' => $p['location']->public_id, 'price_minor' => 8000]]]);
    $this->actingAs($p['user'])->put(route('business.services.update', [$p['business'], $p['service']]), $data)->assertSessionHasNoErrors();
    $effective = app(EffectiveServiceResolver::class)->resolve($p['service']->fresh(), $p['staff'], $p['location']);
    expect($effective->priceMinor)->toBe(9000)->and($effective->durationMinutes)->toBe(45)->and($appointment->serviceLines()->first()->configuration_snapshot)->toBe($snapshot)
        ->and($p['service']->staffAssignments()->count())->toBe(2)->and($p['service']->locations()->first()->pivot->price_minor)->toBe(8000);
    $this->put(route('business.services.update', [$p['business'], $p['service']]), $data)->assertSessionHasNoErrors();
    expect($p['service']->staffAssignments()->count())->toBe(2)->and(AuditEvent::query()->where('action', 'service.catalog.updated')->count())->toBe(1);
});

it('does not reactivate an inactive service or publish an internal service on restore', function () {
    $p = operationalPath();
    $p['service']->update(['is_active' => false, 'online_visible' => false]);
    $this->actingAs($p['user'])->put(route('business.services.update', [$p['business'], $p['service']]), catalogData($p, ['name' => 'Renamed']))->assertSessionHasNoErrors();
    expect($p['service']->fresh()->is_active)->toBeFalse();
    $data = catalogData($p);
    $this->patch(route('business.services.status', [$p['business'], $p['service']]), ['active' => true, 'revision' => $data['revision'], 'impact_revision' => $data['impact_revision']])->assertSessionHasNoErrors();
    expect($p['service']->fresh()->online_visible)->toBeFalse();
});

it('rejects stale configuration, changed appointment reviews and unreviewed price edits', function () {
    $p = operationalPath();
    $data = catalogData($p, ['price_minor' => 4000]);
    $a = operationalBooking($p, '2035-10-10 10:00', 'catalog-stale-impact');
    $url = route('business.services.update', [$p['business'], $p['service']]);
    $this->actingAs($p['user'])->put($url, $data)->assertSessionHasErrors('impact_revision');
    $this->put($url, catalogData($p, ['price_minor' => 4000]))->assertSessionHasErrors('reason');
    $data = catalogData($p, ['price_minor' => 4000, 'reason' => 'Reviewed']);
    $p['service']->update(['name' => 'Changed elsewhere']);
    $this->put($url, $data)->assertSessionHasErrors('revision');
    expect($a->fresh()->version)->toBe(1)->and($p['service']->fresh()->price_minor)->toBe(3000);
});

it('preserves dated staff variants and prevents edits during live booking holds', function () {
    $p = operationalPath();
    $future = StaffServiceAssignment::query()->create(['business_id' => $p['business']->id, 'service_id' => $p['service']->id, 'staff_profile_id' => $p['staff']->id, 'is_active' => true, 'is_qualified' => true, 'effective_from' => now()->addDay(), 'price_minor' => 9999]);
    $url = route('business.services.update', [$p['business'], $p['service']]);
    $this->actingAs($p['user'])->put($url, catalogData($p, ['name' => 'New name']))->assertSessionHasErrors('revision');
    expect($future->fresh()->is_active)->toBeTrue();
    $future->update(['is_active' => false]);
    app(CapacityHoldCommand::class)->hold(new BookingRequest($p['business']->id, $p['location']->id, CarbonImmutable::parse('2035-10-10 10:00', 'Asia/Kolkata')->utc(), [new BookingLineRequest($p['service']->id, $p['staff']->id)], 'online', 'existing', CarbonImmutable::now()), 'catalog-live-hold');
    $this->put($url, catalogData($p, ['name' => 'New name']))->assertSessionHasErrors('revision');
});

it('clears zeroed processing and cleanup in effective segments without losing resource identities', function () {
    $p = operationalPath();
    $p['service']->update(['processing_minutes' => 20, 'cleanup_minutes' => 10]);
    foreach ([['active', 60, true], ['processing', 20, false], ['cleanup', 10, true]] as $i => [$kind,$minutes,$occupies]) {
        ServiceSegment::query()->create(['business_id' => $p['business']->id, 'service_id' => $p['service']->id, 'kind' => $kind, 'duration_minutes' => $minutes, 'sequence' => $i + 1, 'occupies_staff' => $occupies]);
    }
    $this->actingAs($p['user'])->put(route('business.services.update', [$p['business'], $p['service']]), catalogData($p, ['processing_minutes' => 0, 'cleanup_minutes' => 0]))->assertSessionHasNoErrors();
    $effective = app(EffectiveServiceResolver::class)->resolve($p['service']->fresh(), $p['staff'], $p['location']);
    expect($effective->bookableMinutes)->toBe(60)->and(array_column($effective->segments, 'kind'))->toBe(['active'])->and($p['service']->segments()->count())->toBe(3);
});

it('creates an incomplete service safely, replays once, and refuses duplicate names', function () {
    $p = operationalPath();
    $data = catalogData($p, ['name' => 'New service', 'staff_ids' => [], 'location_ids' => [], 'revision' => null, 'impact_revision' => null]);
    $this->actingAs($p['user'])->post(route('business.services.store', $p['business']), $data)->assertSessionHasNoErrors();
    $this->post(route('business.services.store', $p['business']), $data)->assertSessionHasNoErrors();
    expect($p['business']->services()->where('name', 'New service')->count())->toBe(1);
    $this->post(route('business.services.store', $p['business']), [...$data, 'command_key' => (string) Str::uuid()])->assertSessionHasErrors('name');
    $this->post(route('business.services.store', $p['business']), [...$data, 'name' => 'Tampered replay'])->assertSessionHasErrors('command_key');
});

it('validates money and assignments, denies reception and isolates tenant references', function () {
    $p = operationalPath();
    $other = operationalPath();
    $url = route('business.services.update', [$p['business'], $p['service']]);
    $this->actingAs($p['user'])->put($url, catalogData($p, ['staff_ids' => [$other['staff']->public_id]]))->assertNotFound();
    $this->put($url, catalogData($p, ['deposit_type' => 'fixed', 'deposit_value' => 999999]))->assertSessionHasErrors('deposit_value');
    $this->put($url, catalogData($p, ['duration_minutes' => 0]))->assertSessionHasErrors('duration_minutes');
    $this->put($url, catalogData($p, ['location_overrides' => [['location' => $other['location']->public_id, 'price_minor' => 0]]]))->assertSessionHasErrors('location_overrides');
    $reception = operationalPath(StarterRole::Receptionist);
    $this->actingAs($reception['user'])->get(route('business.services.index', $reception['business']))->assertForbidden();
    $this->post(route('business.services.store', $reception['business']), catalogData($reception))->assertForbidden();
});

it('manages categories inline without deleting historical relationships', function () {
    $p = operationalPath();
    $cat = ServiceCategory::query()->create(['business_id' => $p['business']->id, 'name' => 'Cuts']);
    $p['service']->update(['service_category_id' => $cat->id]);
    $url = route('business.services.categories.save', $p['business']);
    $this->actingAs($p['user'])->post($url, ['category_id' => $cat->public_id, 'name' => 'Hair', 'is_active' => false, 'revision' => app(ServiceCatalogManager::class)->categoryRevision($cat)])->assertSessionHasErrors('is_active');
    $this->post($url, ['category_id' => $cat->public_id, 'name' => 'Hair', 'is_active' => true, 'revision' => app(ServiceCatalogManager::class)->categoryRevision($cat)])->assertSessionHasNoErrors();
    expect($p['service']->fresh()->service_category_id)->toBe($cat->id)->and($cat->fresh()->name)->toBe('Hair');
});

it('includes newly introduced staff processing and cleanup in canonical segments', function () {
    $p = operationalPath();
    $segment = ServiceSegment::query()->create(['business_id' => $p['business']->id, 'service_id' => $p['service']->id, 'kind' => 'active', 'duration_minutes' => 60, 'sequence' => 1, 'occupies_staff' => true]);
    $p['service']->staffAssignments()->first()->update(['duration_minutes' => 40, 'processing_minutes' => 20, 'cleanup_minutes' => 5]);
    $resolved = app(EffectiveServiceResolver::class)->resolve($p['service']->fresh(), $p['staff'], $p['location']);
    expect(array_column($resolved->segments, 'duration_minutes'))->toBe([40, 20, 5])->and(array_column($resolved->segments, 'occupies_staff'))->toBe([true, false, true])->and($resolved->segments[0]['id'])->toBe($segment->id)->and($resolved->bookableMinutes)->toBe(65);
});

it('duplicates segments and resource configuration with new identities but no appointment history', function () {
    $p = operationalPath();
    $segment = ServiceSegment::query()->create(['business_id' => $p['business']->id, 'service_id' => $p['service']->id, 'kind' => 'active', 'duration_minutes' => 60, 'sequence' => 1, 'occupies_staff' => true]);
    $resource = PhysicalResource::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'name' => 'Colour chair', 'type' => 'chair', 'quantity' => 1, 'is_active' => true]);
    $p['service']->resourceRequirements()->create(['business_id' => $p['business']->id, 'physical_resource_id' => $resource->id, 'service_segment_id' => $segment->id, 'quantity' => 1]);
    $data = catalogData($p, ['duplicate_of' => $p['service']->public_id, 'name' => 'Signature copy', 'is_active' => false, 'online_visible' => false, 'revision' => null, 'impact_revision' => null]);
    $this->actingAs($p['user'])->post(route('business.services.store', $p['business']), $data)->assertSessionHasNoErrors();
    $copy = $p['business']->services()->where('name', 'Signature copy')->firstOrFail();
    expect($copy->public_id)->not->toBe($p['service']->public_id)->and($copy->segments()->first()->id)->not->toBe($segment->id)->and($copy->resourceRequirements()->first()->service_segment_id)->toBe($copy->segments()->first()->id)->and($copy->is_active)->toBeFalse();
    expect(AppointmentServiceLine::where('service_id', $copy->id)->count())->toBe(0);
});

it('prevents a branch manager from changing shared staff capabilities through service commands', function () {
    $p = operationalPath(StarterRole::Manager);
    $otherBranch = Location::factory()->create(['business_id' => $p['business']->id]);
    $p['staff']->locations()->attach($otherBranch->id, ['business_id' => $p['business']->id]);
    $this->actingAs($p['user'])->put(route('business.services.update', [$p['business'], $p['service']]), catalogData($p, ['staff_ids' => []]))->assertForbidden();
    $data = catalogData($p);
    $this->patch(route('business.services.status', [$p['business'], $p['service']]), ['active' => false, 'revision' => $data['revision'], 'impact_revision' => $data['impact_revision']])->assertForbidden();
});

it('projects qualified Calendar variants and prevents invalid added checkout add-ons', function () {
    $p = operationalPath();
    $p['service']->staffAssignments()->first()->update(['duration_minutes' => 40, 'price_minor' => 9000]);
    $this->actingAs($p['user'])->get(route('business.calendar', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('options.services.0.staff_variants.0.price_minor', 9000)->where('options.services.0.staff_variants.0.duration_minutes', 40));
    $t = checkoutFixture();
    $addon = Service::query()->create(['business_id' => $t['business']->id, 'name' => 'Treatment add-on', 'kind' => 'addon', 'currency_code' => 'INR', 'price_minor' => 1000, 'duration_minutes' => 10]);
    $addon->locations()->attach($t['location']->id, ['business_id' => $t['business']->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create(['business_id' => $t['business']->id, 'service_id' => $addon->id, 'staff_profile_id' => $t['staff']->id, 'is_qualified' => true, 'is_active' => true]);
    $basket = $t['basket'];
    $basket['items'][] = ['service_public_id' => $addon->public_id, 'staff_profile_id' => $t['staff']->id, 'quantity' => 1];
    $this->actingAs($t['user'])->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $basket)->assertUnprocessable();
    $t['service']->addons()->attach($addon->id, ['business_id' => $t['business']->id]);
    $this->postJson(route('business.checkout.preview', [$t['business'], $t['appointment']]), $basket)->assertOk()->assertJsonPath('quote.lines.1.unit_price_minor', 1000);
});

it('places introduced base processing before cleanup while retaining segment identities', function () {
    $p = operationalPath();
    $p['service']->update(['cleanup_minutes' => 5]);
    $active = $p['service']->segments()->create(['business_id' => $p['business']->id, 'kind' => 'active', 'sequence' => 1, 'duration_minutes' => 60, 'occupies_staff' => true]);
    $cleanup = $p['service']->segments()->create(['business_id' => $p['business']->id, 'kind' => 'cleanup', 'sequence' => 2, 'duration_minutes' => 5, 'occupies_staff' => true]);
    $this->actingAs($p['user'])->put(route('business.services.update', [$p['business'], $p['service']]), catalogData($p, ['processing_minutes' => 20]))->assertSessionHasNoErrors();
    $resolved = app(EffectiveServiceResolver::class)->resolve($p['service']->fresh(), $p['staff'], $p['location']);
    expect(array_column($resolved->segments, 'kind'))->toBe(['active', 'processing', 'cleanup'])->and($resolved->segments[0]['id'])->toBe($active->id)->and($resolved->segments[2]['id'])->toBe($cleanup->id);
});

it('validates imported duration and currency and blocks unreviewed existing-service overwrites', function () {
    $p = operationalPath();
    Storage::fake('private');
    app(TenantContext::class)->activate($p['business'], $p['membership']);
    $imports = app(ConfigurationImportService::class);
    $csv = "name,price,time,currency\nNew valid service,1200,30,INR\nZero duration,0,0,INR\nWrong currency,1000,30,USD\n".$p['service']->name.',9000,90,INR';
    $import = $imports->preview($p['business'], 'services', 'catalog-import-test', 'services.csv', $csv, ['name' => 'name', 'price_minor' => 'price', 'duration_minutes' => 'time', 'currency_code' => 'currency']);
    expect($import->failed_rows)->toBe(2)->and($import->duplicate_rows)->toBe(1)->and($import->error_export_path)->not->toBeNull();
    $duplicate = $import->rows->firstWhere('status', 'duplicate_review');
    expect(fn () => $imports->commit($import, [$duplicate->id => 'update']))->toThrow(ValidationException::class);
    $result = $imports->commit($import, [$duplicate->id => 'skip']);
    expect($result->created_rows)->toBe(1)->and($result->failed_rows)->toBe(2)->and($p['service']->fresh()->price_minor)->toBe(3000);
    $imports->commit($result);
    expect($p['business']->services()->where('name', 'New valid service')->count())->toBe(1);
});

it('presents service changes in Activity without exposing descriptions or rewriting audit history', function () {
    $event = new AuditEvent(['action' => 'service.catalog.updated', 'before' => ['price_minor' => 1000, 'duration_minutes' => 30], 'after' => ['price_minor' => 1500, 'duration_minutes' => 45, 'currency_code' => 'INR', 'description' => 'Private operational details']]);
    $presentation = app(AuditEventPresentation::class);
    expect($presentation->label($event))->toBe('Service configuration changed')->and($presentation->summary($event))->toContain('Base price: INR 10.00 → INR 15.00', 'Active time: 30 min → 45 min')->and($presentation->summary($event))->not->toContain('Private operational details');
});

it('loads larger catalogues without per-service query growth', function () {
    $p = operationalPath();
    $this->actingAs($p['user']);
    DB::enableQueryLog();
    $this->get(route('business.services.index', $p['business']))->assertOk();
    $small = count(DB::getQueryLog());
    DB::disableQueryLog();
    for ($i = 0; $i < 40; $i++) {
        $s = Service::query()->create(['business_id' => $p['business']->id, 'name' => 'Volume service '.$i, 'kind' => 'service', 'currency_code' => 'INR', 'price_minor' => 1000, 'duration_minutes' => 30]);
        $s->locations()->attach($p['location']->id, ['business_id' => $p['business']->id, 'is_eligible' => true]);
        StaffServiceAssignment::query()->create(['business_id' => $p['business']->id, 'service_id' => $s->id, 'staff_profile_id' => $p['staff']->id, 'is_active' => true, 'is_qualified' => true]);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('business.services.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('services', 41));
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($large)->toBeLessThanOrEqual($small + 2);
});

it('retains inactive branch and professional configuration without making new work bookable', function () {
    $p = operationalPath();
    $p['location']->update(['is_active' => false, 'status' => 'inactive']);
    $p['staff']->update(['status' => 'inactive']);
    $this->actingAs($p['user'])->put(route('business.services.update', [$p['business'], $p['service']]), catalogData($p, ['name' => 'Updated reference']))->assertSessionHasNoErrors();
    expect($p['service']->locations()->count())->toBe(1)->and($p['service']->staffAssignments()->where('is_active', true)->where('is_qualified', true)->count())->toBe(1);
    expect(fn () => app(EffectiveServiceResolver::class)->resolve($p['service']->fresh(), $p['staff']->fresh(), $p['location']->fresh()))->toThrow(ValidationException::class);
    $new = catalogData($p, ['name' => 'New inactive capability', 'revision' => null, 'impact_revision' => null]);
    $this->post(route('business.services.store', $p['business']), $new)->assertSessionHasErrors('location_ids');
});

it('projects current public staff and branch prices without private staff information', function () {
    $p = publicBookingPath();
    $p['service']->staffAssignments()->first()->update(['price_minor' => 5000, 'duration_minutes' => 45, 'processing_minutes' => 10, 'cleanup_minutes' => 5]);
    $p['service']->locations()->updateExistingPivot($p['location']->id, ['price_minor' => 4000]);
    $catalog = app(PublicBookingService::class)->catalog($p['business']);
    $service = collect($catalog['services'])->firstWhere('public_id', $p['service']->public_id);
    $staff = collect($catalog['staff'])->firstWhere('public_id', $p['staff']->public_id);
    expect(collect($service['location_prices'])->first()['price_minor'])->toBe(4000)->and(collect($staff['service_variants'])->first()['price_minor'])->toBe(5000)->and(collect($staff['service_variants'])->first()['duration_minutes'])->toBe(45)->and(array_key_exists('email', $staff))->toBeFalse()->and(array_key_exists('commission_rate', collect($staff['service_variants'])->first()))->toBeFalse();
});
