<?php

use App\Domain\BusinessConfiguration\Models\ConfigurationChangePreview;
use App\Domain\BusinessConfiguration\Models\LocationScheduleException;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\BusinessConfiguration\Services\StaffScheduleValidator;
use App\Domain\BusinessConfiguration\Services\StaffWorkforceManager;
use App\Domain\Commissions\Services\CommissionLedger;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Services\CalendarWorkspaceQuery;
use App\Domain\SchedulingOperations\Services\TeamWorkspaceQuery;
use App\Domain\SchedulingOperations\Services\WalkInWorkspaceQuery;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    CarbonImmutable::setTestNow('2035-10-09 04:30 UTC');
});
afterEach(fn () => CarbonImmutable::setTestNow());

function workforceEdit(array $p, array $extra = []): array
{
    $staff = $p['staff']->fresh(['locations', 'availabilityRules']);
    $service = app(StaffWorkforceManager::class);

    return ['rules' => [...$service->rules($staff), ...$extra], 'revision' => $service->revision($staff)];
}
function workforceLeave(array $p, string $date = '2035-10-10'): array
{
    return ['kind' => 'leave', 'location_id' => $p['location']->id, 'starts_on' => $date, 'ends_on' => $date, 'reason' => 'Private test reason'];
}

it('projects workforce coverage from the same configuration as Calendar and walk-ins', function () {
    $p = operationalPath();
    StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'staff_profile_id' => $p['staff']->id, 'kind' => 'break', 'day_of_week' => 2, 'starts_at' => '12:00', 'ends_at' => '13:00', 'reason' => 'Private reason']);
    $this->actingAs($p['user'])->get(route('business.team.index', ['business' => $p['business'], 'date' => '2035-10-09']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Team/Index')->where('can.manage', true)->has('schedule.days', 7)->has('staff', 1)->where('staff.0.display_name', 'Avery')->has('staff.0.revision'));
    $staff = collect([$p['staff']->fresh(['locations', 'availabilityRules'])]);
    $schedule = app(CalendarWorkspaceQuery::class)->build($p['location'], $staff, CarbonImmutable::parse('2035-10-09', 'Asia/Kolkata'), 1);
    $queue = app(WalkInWorkspaceQuery::class)->build($p['location'], $staff, collect(), collect(), $schedule, CarbonImmutable::parse('2035-10-09 12:30', 'Asia/Kolkata'), 15);
    expect($queue['team'][0]['state'])->toBe('Break')->and($queue['team'][0]['available_now'])->toBeFalse();
});

it('allows reception read-only coverage and limits a professional to their own linked profile', function () {
    foreach ([StarterRole::Receptionist, StarterRole::BarberStylist] as $role) {
        $p = operationalPath($role);
        $other = StaffProfile::factory()->create(['business_id' => $p['business']->id, 'email' => 'hidden@example.test']);
        $other->locations()->syncWithPivotValues([$p['location']->id], ['business_id' => $p['business']->id]);
        StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'staff_profile_id' => $p['staff']->id, 'kind' => 'leave', 'starts_on' => '2035-10-10', 'ends_on' => '2035-10-10', 'reason' => 'Private medical detail']);
        $response = $this->actingAs($p['user'])->get(route('business.team.index', ['business' => $p['business'], 'date' => '2035-10-09']));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.manage', false)->has('staff', $role === StarterRole::BarberStylist ? 1 : 2)->where('accessRoles', [])->where('pendingInvitations', []));
        expect($response->getContent())->not->toContain('Private medical detail');
        $this->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), workforceEdit($p))->assertForbidden();
    }
});

it('fails closed for own-calendar access without a staff profile and rejects another tenant staff ID', function () {
    $p = operationalPath(StarterRole::BarberStylist);
    $p['staff']->update(['membership_id' => null]);
    $this->actingAs($p['user'])->get(route('business.team.index', $p['business']))->assertForbidden();
    $owner = operationalPath();
    $this->actingAs($owner['user'])->postJson(route('business.team.schedule.preview', [$owner['business'], $p['staff']]), workforceEdit($p))->assertNotFound();
});

it('previews leave conflicts, requires a retained exception and saves exactly once without changing the appointment', function () {
    $p = operationalPath();
    $a = operationalBooking($p, '2035-10-10 10:00', 'workforce-impact');
    $edit = workforceEdit($p, [workforceLeave($p)]);
    $preview = $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->assertOk()->assertJsonPath('affected_count', 1)->assertJsonPath('appointments.0.id', $a->public_id)->json();
    $command = [...$edit, 'impact_preview_id' => $preview['public_id']];
    $url = route('business.team.schedule.update', [$p['business'], $p['staff']]);
    $this->put($url, $command)->assertSessionHasErrors('impact_reason');
    $command['impact_reason'] = 'Reception will reassign this visit after client confirmation.';
    $this->put($url, $command)->assertSessionHasNoErrors()->assertRedirect();
    $this->put($url, $command)->assertSessionHasNoErrors()->assertRedirect();
    expect($a->fresh()->status)->toBe('confirmed')->and($a->fresh()->version)->toBe(1)
        ->and(AuditEvent::query()->where('business_id', $p['business']->id)->where('action', 'configuration.staff_availability.updated')->count())->toBe(1);
    $schedule = app(CalendarWorkspaceQuery::class)->build($p['location'], collect([$p['staff']->fresh()]), CarbonImmutable::parse('2035-10-10', 'Asia/Kolkata'), 1);
    expect($schedule['days'][0]['staff'][0]['windows'])->toBe([]);
});

it('rejects a changed proposal, an expired preview and a stale schedule revision', function () {
    $p = operationalPath();
    $edit = workforceEdit($p, [workforceLeave($p)]);
    $preview = $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->json();
    $url = route('business.team.schedule.update', [$p['business'], $p['staff']]);
    $changed = $edit;
    $changed['rules'][0]['starts_at'] = '10:00';
    $this->put($url, [...$changed, 'impact_preview_id' => $preview['public_id']])->assertSessionHasErrors('preview');
    ConfigurationChangePreview::query()->where('public_id', $preview['public_id'])->update(['expires_at' => now()->subMinute()]);
    $this->put($url, [...$edit, 'impact_preview_id' => $preview['public_id']])->assertSessionHasErrors('preview');
    $p['staff']->availabilityRules()->first()->update(['starts_at' => '10:00']);
    $this->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->assertUnprocessable()->assertJsonValidationErrors('revision');
});

it('invalidates a review when a booking arrives or changes after the preview', function () {
    $p = operationalPath();
    $edit = workforceEdit($p, [workforceLeave($p)]);
    $preview = $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->json();
    $a = operationalBooking($p, '2035-10-10 10:00', 'new-booking-during-review');
    $this->put(route('business.team.schedule.update', [$p['business'], $p['staff']]), [...$edit, 'impact_preview_id' => $preview['public_id'], 'impact_reason' => 'Retained'])->assertSessionHasErrors('preview');
    $preview = $this->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->json();
    $a->update(['version' => 2]);
    $this->put(route('business.team.schedule.update', [$p['business'], $p['staff']]), [...$edit, 'impact_preview_id' => $preview['public_id'], 'impact_reason' => 'Retained'])->assertSessionHasErrors('preview');
});

it('validates rule shapes, dates, reversed times and recurring overlapping breaks on the server', function () {
    $p = operationalPath();
    foreach ([['kind' => 'leave'], ['kind' => 'break', 'day_of_week' => 1, 'starts_at' => '13:00'], ['kind' => 'leave', 'starts_on' => '2035-10-10', 'ends_on' => '2035-10-09']] as $invalid) {
        $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), workforceEdit($p, [$invalid]))->assertUnprocessable();
    }
    $rules = [['kind' => 'break', 'day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '13:00'], ['kind' => 'break', 'starts_on' => '2035-10-08', 'ends_on' => '2035-10-08', 'starts_at' => '12:30', 'ends_at' => '13:30']];
    $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), workforceEdit($p, $rules))->assertUnprocessable()->assertJsonValidationErrors('rules');
});

it('allows split shifts and same-branch temporary replacement but detects absolute cross-timezone collisions', function () {
    $p = operationalPath();
    $other = Location::factory()->create(['business_id' => $p['business']->id, 'time_zone' => 'Europe/London']);
    $rules = [['kind' => 'working', 'location_id' => $p['location']->id, 'day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'], ['kind' => 'working', 'location_id' => $p['location']->id, 'day_of_week' => 1, 'starts_at' => '13:00', 'ends_at' => '17:00'], ['kind' => 'temporary_change', 'location_id' => $p['location']->id, 'starts_on' => '2035-10-08', 'ends_on' => '2035-10-08', 'starts_at' => '10:00', 'ends_at' => '16:00']];
    $validator = app(StaffScheduleValidator::class);
    $validator->validate($rules, collect([$p['location'], $other]));
    expect(fn () => $validator->validate([...$rules, ['kind' => 'working', 'location_id' => $other->id, 'day_of_week' => 1, 'starts_at' => '04:00', 'ends_at' => '12:00']], collect([$p['location'], $other])))->toThrow(ValidationException::class, 'two branches');
});

it('denies manager changes at unassigned branches and disallows an unassigned rule location', function () {
    $p = operationalPath(StarterRole::Manager);
    $other = Location::factory()->create(['business_id' => $p['business']->id]);
    $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), workforceEdit($p, [['kind' => 'working', 'day_of_week' => 3, 'location_id' => $other->id, 'starts_at' => '18:00', 'ends_at' => '19:00']]))->assertUnprocessable();
    $p['staff']->locations()->attach($other->id, ['business_id' => $p['business']->id]);
    $this->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), workforceEdit($p))->assertForbidden();
    $this->get(route('business.team.index', ['business' => $p['business'], 'location' => $other->public_id]))->assertNotFound();
});

it('warns about out-of-business hours without silently changing the entered schedule', function () {
    $p = operationalPath();
    $edit = workforceEdit($p);
    $edit['rules'][0]['ends_at'] = '20:00';
    $preview = $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->assertOk()->json();
    expect($preview['outside_hours'])->not->toBeEmpty();
    $this->put(route('business.team.schedule.update', [$p['business'], $p['staff']]), [...$edit, 'impact_preview_id' => $preview['public_id']])->assertSessionHasNoErrors();
    expect($p['staff']->availabilityRules()->where('day_of_week', 1)->first()->ends_at)->toStartWith('20:00');
});

it('versions service variants and preserves existing appointment price and duration snapshots', function () {
    $p = operationalPath();
    $a = operationalBooking($p, '2035-10-10 10:00', 'unchanged-service-snapshot');
    $before = $a->serviceLines()->first()->configuration_snapshot;
    $staff = $p['staff']->fresh(['serviceAssignments']);
    $revision = app(StaffWorkforceManager::class)->servicesRevision($staff);
    $data = ['assignments' => [['service' => $p['service']->public_id, 'is_qualified' => true, 'online_visible' => false, 'duration_minutes' => 45, 'price_minor' => 4500]], 'services_revision' => $revision];
    $this->actingAs($p['user'])->put(route('business.team.services.update', [$p['business'], $p['staff']]), $data)->assertSessionHasNoErrors();
    expect($p['staff']->serviceAssignments()->count())->toBe(2)->and($a->serviceLines()->first()->configuration_snapshot)->toBe($before);
    $this->put(route('business.team.services.update', [$p['business'], $p['staff']]), $data)->assertSessionHasErrors('services_revision');
});

it('requires a reason before removing a capability used by future appointments', function () {
    $p = operationalPath();
    operationalBooking($p, '2035-10-10 10:00', 'qualification-removal');
    $staff = $p['staff']->fresh(['serviceAssignments']);
    $data = ['assignments' => [], 'services_revision' => app(StaffWorkforceManager::class)->servicesRevision($staff)];
    $this->actingAs($p['user'])->put(route('business.team.services.update', [$p['business'], $p['staff']]), $data)->assertSessionHasErrors('reason');
    $this->put(route('business.team.services.update', [$p['business'], $p['staff']]), [...$data, 'reason' => 'Will reassign booked visits.'])->assertSessionHasNoErrors();
});

it('keeps login-only accounts visible alongside working profiles', function () {
    $p = operationalPath();
    $other = Location::factory()->create(['business_id' => $p['business']->id]);
    $p['staff']->update(['membership_id' => null]);
    $this->actingAs($p['user'])->get(route('business.team.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('accounts', 1)->where('accounts.0.user_name', $p['user']->name));
});

it('creates a minimal working profile without hours and prevents privilege errors from leaving an orphan', function () {
    $p = operationalPath(StarterRole::Manager);
    $data = ['display_name' => 'New colleague', 'email' => 'new-colleague@example.test', 'online_visible' => false, 'location' => $p['location']->public_id, 'working_days' => [], 'starts_at' => '09:00', 'ends_at' => '18:00', 'service_ids' => [], 'invite_access' => false];
    $this->actingAs($p['user'])->post(route('business.team.providers.store', $p['business']), $data)->assertSessionHasNoErrors();
    expect($p['business']->staffProfiles()->where('email', $data['email'])->first()->availabilityRules()->count())->toBe(0);
    $role = BusinessRole::query()->where('business_id', $p['business']->id)->where('name', 'owner')->firstOrFail();
    $this->post(route('business.team.providers.store', $p['business']), [...$data, 'email' => 'denied@example.test', 'invite_access' => true, 'access_role_id' => $role->id])->assertForbidden();
    expect($p['business']->staffProfiles()->where('email', 'denied@example.test')->exists())->toBeFalse();
});

function workforceProfile(array $p, array $changes = []): array
{
    $staff = $p['staff']->fresh(['locations']);

    return [...$staff->only(['display_name', 'title', 'email', 'mobile', 'biography', 'status', 'online_visible']),
        'location_ids' => $staff->locations->pluck('public_id')->all(),
        'profile_revision' => app(StaffWorkforceManager::class)->profileRevision($staff), ...$changes];
}

it('protects live holds from schedule reductions, deactivation and qualification removal', function () {
    $p = operationalPath();
    $hold = app(CapacityHoldCommand::class)->hold(new BookingRequest(
        $p['business']->id, $p['location']->id, CarbonImmutable::parse('2035-10-10 10:00', 'Asia/Kolkata')->utc(),
        [new BookingLineRequest($p['service']->id, $p['staff']->id, [], false)], 'reception', 'existing', CarbonImmutable::now()->utc()
    ), 'workforce-active-hold');
    $edit = workforceEdit($p, [workforceLeave($p)]);
    $preview = $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->assertOk()->assertJsonPath('held_count', 1)->json();
    $this->put(route('business.team.schedule.update', [$p['business'], $p['staff']]), [...$edit, 'impact_preview_id' => $preview['public_id']])->assertSessionHasErrors('preview');
    $this->patch(route('business.team.profile.update', [$p['business'], $p['staff']]), workforceProfile($p, ['status' => 'inactive', 'reason' => 'Leave']))->assertSessionHasErrors('status');
    $this->put(route('business.team.services.update', [$p['business'], $p['staff']]), ['assignments' => [], 'services_revision' => app(StaffWorkforceManager::class)->servicesRevision($p['staff']->fresh(['serviceAssignments']))])->assertSessionHasErrors('assignments');
    expect($hold->fresh()->status)->toBe('active')->and($p['staff']->fresh()->status)->toBe('active');
});

it('preserves dated service changes and rejects inactive new qualifications', function () {
    $p = operationalPath();
    $variant = StaffServiceAssignment::query()->create(['business_id' => $p['business']->id, 'staff_profile_id' => $p['staff']->id, 'service_id' => $p['service']->id, 'is_active' => true, 'is_qualified' => true, 'duration_minutes' => 90, 'effective_from' => now()->addWeek()]);
    $data = ['assignments' => [], 'services_revision' => app(StaffWorkforceManager::class)->servicesRevision($p['staff']->fresh(['serviceAssignments']))];
    $this->actingAs($p['user'])->put(route('business.team.services.update', [$p['business'], $p['staff']]), $data)->assertSessionHasErrors('assignments');
    expect($variant->fresh()->is_active)->toBeTrue()->and($variant->fresh()->duration_minutes)->toBe(90);
    $p['staff']->serviceAssignments()->update(['is_active' => false]);
    $p['service']->update(['is_active' => false]);
    $this->put(route('business.team.services.update', [$p['business'], $p['staff']]), ['assignments' => [['service' => $p['service']->public_id, 'is_qualified' => true, 'online_visible' => true]], 'services_revision' => app(StaffWorkforceManager::class)->servicesRevision($p['staff']->fresh(['serviceAssignments']))])->assertSessionHasErrors('assignments');
});

it('requires a reason for deactivation and preserves visits and login access', function () {
    $p = operationalPath();
    $a = operationalBooking($p, '2035-10-10 10:00', 'deactivate-with-visits');
    $data = workforceProfile($p, ['status' => 'inactive']);
    $this->actingAs($p['user'])->patch(route('business.team.profile.update', [$p['business'], $p['staff']]), $data)->assertSessionHasErrors('reason');
    $this->patch(route('business.team.profile.update', [$p['business'], $p['staff']]), [...$data, 'reason' => 'Manager will review the scheduled visit.'])->assertSessionHasNoErrors();
    expect($a->fresh()->status)->toBe('confirmed')->and($p['membership']->fresh()->isActive())->toBeTrue()->and($p['staff']->fresh()->status)->toBe('inactive');
    $this->patch(route('business.team.profile.update', [$p['business'], $p['staff']]), $data)->assertSessionHasErrors('profile_revision');
});

it('changes permitted login branches without altering staff work locations', function () {
    Notification::fake();
    $p = operationalPath();
    $branch = Location::factory()->create(['business_id' => $p['business']->id]);
    $colleague = Membership::factory()->create(['business_id' => $p['business']->id]);
    app(MembershipAccessManager::class)->assignStarterRole($colleague, StarterRole::BarberStylist, $p['user'], 'Fixture');
    $colleague->locations()->syncWithPivotValues([$p['location']->id], ['business_id' => $p['business']->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $p['business']->id, 'membership_id' => $colleague->id]);
    $staff->locations()->syncWithPivotValues([$p['location']->id], ['business_id' => $p['business']->id]);
    $role = BusinessRole::query()->where('business_id', $p['business']->id)->where('name', 'barber_stylist')->firstOrFail();
    $this->actingAs($p['user'])->patch(route('business.team.memberships.access.update', [$p['business'], $colleague]), ['custom_access' => false, 'access_role_id' => $role->id, 'location_ids' => [$branch->public_id], 'reason' => 'Login scope only'])->assertSessionHasNoErrors();
    expect($staff->locations()->pluck('locations.id')->all())->toBe([$p['location']->id])->and($colleague->locations()->pluck('locations.id')->all())->toBe([$branch->id]);
});

it('warns about dated branch closures and handles matching times stored with seconds', function () {
    $p = operationalPath();
    $edit = workforceEdit($p);
    $preview = $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->assertOk()->json();
    expect($preview['outside_hours'])->toBe([]);
    LocationScheduleException::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'kind' => 'closure', 'name' => 'Holiday closure', 'starts_on' => '2035-10-10', 'ends_on' => '2035-10-10']);
    $edit = workforceEdit($p, [['kind' => 'temporary_change', 'location_id' => $p['location']->id, 'starts_on' => '2035-10-10', 'ends_on' => '2035-10-10', 'starts_at' => '10:00', 'ends_at' => '16:00']]);
    $warnings = $this->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->assertOk()->json('outside_hours');
    expect(collect($warnings)->contains(fn ($w) => str_contains($w, '2035-10-10')))->toBeTrue();
});

it('accepts exact normalized schedules when a JSON database reorders object keys', function () {
    $p = operationalPath();
    $edit = workforceEdit($p, [workforceLeave($p)]);
    $preview = $this->actingAs($p['user'])->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->assertOk()->json();
    $stored = ConfigurationChangePreview::query()->where('public_id', $preview['public_id'])->firstOrFail();
    $proposal = $stored->proposed_change;
    $proposal['rules'] = array_map(fn ($rule) => array_reverse($rule, true), $proposal['rules']);
    $stored->update(['proposed_change' => $proposal]);
    $this->put(route('business.team.schedule.update', [$p['business'], $p['staff']]), [...$edit, 'impact_preview_id' => $preview['public_id']])->assertSessionHasNoErrors();
    expect($p['staff']->availabilityRules()->where('kind', 'leave')->count())->toBe(1);
});

it('loads more staff without adding per-person schedule or permission queries', function () {
    $p = operationalPath();
    $query = app(TeamWorkspaceQuery::class);
    $count = function () use ($p, $query) {
        return app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p, $query) {
            $p['membership']->hasPermissionTo('staff.manage', 'web');
            DB::enableQueryLog();
            DB::flushQueryLog();
            $data = $query->build(Request::create('/', 'GET', ['date' => '2035-10-09']), $p['business'], $p['membership']);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$queries, count($data['staff'])];
        });
    };
    [$before] = $count();
    for ($i = 0; $i < 12; $i++) {
        $member = Membership::factory()->create(['business_id' => $p['business']->id]);
        app(MembershipAccessManager::class)->assignStarterRole($member, StarterRole::BarberStylist, $p['user'], 'Fixture');
        $member->locations()->syncWithPivotValues([$p['location']->id], ['business_id' => $p['business']->id]);
        $staff = StaffProfile::factory()->create(['business_id' => $p['business']->id, 'membership_id' => $member->id]);
        $staff->locations()->syncWithPivotValues([$p['location']->id], ['business_id' => $p['business']->id]);
    }
    [$after, $people] = $count();
    expect($people)->toBe(13)->and($after)->toBeLessThanOrEqual($before + 3);
});

it('presents commission and tip balances in their recorded currencies', function () {
    $p = operationalPath();
    $ledger = app(CommissionLedger::class);
    $ledger->adjustCommission($p['staff'], 1500, 'INR', $p['membership'], 'Fixture', 'workforce-inr');
    $ledger->adjustCommission($p['staff'], 300, 'USD', $p['membership'], 'Fixture', 'workforce-usd');
    $ledger->adjustTip($p['staff'], 100, 'USD', $p['membership'], 'Fixture', 'workforce-usd-tip');
    $statement = $ledger->statement($p['business']->id, $p['staff']->id, now()->startOfDay(), now()->endOfDay());
    expect($statement['totals_by_currency'])->toBe([
        ['currency_code' => 'INR', 'commission_minor' => 1500, 'tips_minor' => 0],
        ['currency_code' => 'USD', 'commission_minor' => 300, 'tips_minor' => 100],
    ]);
    $this->actingAs($p['user'])->getJson(route('business.staff.statement', ['business' => $p['business'], 'staff' => $p['staff'], 'start_date' => '2035-10-09', 'end_date' => '2035-10-09']))
        ->assertOk()->assertJsonPath('statement.totals_by_currency.0.currency_code', 'INR')->assertJsonPath('statement.totals_by_currency.1.currency_code', 'USD');
    $foreign = StaffProfile::factory()->create();
    $this->getJson(route('business.staff.statement', ['business' => $p['business'], 'staff' => $foreign, 'start_date' => '2035-10-09', 'end_date' => '2035-10-09']))->assertNotFound();
});

it('keeps expired schedule history outside the editor and preserves its records on save', function () {
    $p = operationalPath();
    $past = [];
    for ($i = 0; $i < 160; $i++) {
        $past[] = StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'staff_profile_id' => $p['staff']->id, 'location_id' => $p['location']->id, 'kind' => 'leave', 'starts_on' => '2034-01-01', 'ends_on' => '2034-01-01'])->id;
    }
    $this->actingAs($p['user'])->get(route('business.team.index', ['business' => $p['business'], 'date' => '2035-10-09']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('staff.0.availability', 7)->has('upcomingSchedule.days', 4));
    $edit = workforceEdit($p, [workforceLeave($p)]);
    $edit['rules'] = array_values(array_filter($edit['rules'], fn ($r) => ! $r['ends_on'] || $r['ends_on'] >= '2035-10-09'));
    $preview = $this->postJson(route('business.team.schedule.preview', [$p['business'], $p['staff']]), $edit)->assertOk()->json();
    $this->put(route('business.team.schedule.update', [$p['business'], $p['staff']]), [...$edit, 'impact_preview_id' => $preview['public_id']])->assertSessionHasNoErrors();
    expect(StaffAvailabilityRule::query()->whereIn('id', $past)->count())->toBe(160)->and($p['staff']->availabilityRules()->count())->toBe(168);
});
