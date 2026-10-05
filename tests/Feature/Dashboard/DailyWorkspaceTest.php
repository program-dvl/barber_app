<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\Reporting\Services\TodayDashboardService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentSegment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Domain\SchedulingOperations\Services\AppointmentLifecycleService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(fn () => CarbonImmutable::setTestNow());

function dailyWorkspaceFixture(StarterRole $role = StarterRole::Owner, string $zone = 'Asia/Kolkata'): array
{
    [$user,$business,$membership] = createTenantMembership($role);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => $zone]);
    $membership->locations()->attach($location, ['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'membership_id' => $membership->id, 'user_id' => $user->id, 'status' => 'active']);
    $staff->locations()->attach($location, ['business_id' => $business->id]);
    $day = CarbonImmutable::now($zone)->dayOfWeekIso;
    LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => $day, 'sequence' => 1, 'opens_at' => '09:00', 'closes_at' => '19:00']);
    StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'staff_profile_id' => $staff->id, 'kind' => 'working', 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '17:00', 'sequence' => 1]);

    return compact('user', 'business', 'membership', 'location', 'staff');
}

function dailyWorkspaceAppointment(array $path, string $status = 'confirmed', ?StaffProfile $staff = null, ?CarbonImmutable $start = null): Appointment
{
    $start ??= CarbonImmutable::now()->utc();
    $key = (string) Str::ulid();
    $record = Appointment::query()->create(['business_id' => $path['business']->id, 'location_id' => $path['location']->id, 'idempotency_key' => $key, 'request_hash' => hash('sha256', $key), 'status' => $status, 'source' => 'reception', 'starts_at_utc' => $start, 'ends_at_utc' => $start->addMinutes(30), 'time_zone' => $path['location']->time_zone, 'local_starts_at' => $start->setTimezone($path['location']->time_zone)->format(DATE_ATOM), 'local_ends_at' => $start->addMinutes(30)->setTimezone($path['location']->time_zone)->format(DATE_ATOM), 'client_name' => 'Synthetic client', 'client_mobile' => '+919000002001', 'client_email' => 'client@example.test', 'internal_notes' => 'Private appointment context', 'price_minor' => 3000, 'currency_code' => 'INR', 'version' => 1]);
    $service = Service::query()->create(['business_id' => $path['business']->id, 'name' => 'Signature cut', 'kind' => 'service', 'price_minor' => 3000, 'currency_code' => 'INR', 'duration_minutes' => 30, 'is_active' => true]);
    $line = AppointmentServiceLine::query()->create(['business_id' => $path['business']->id, 'appointment_id' => $record->id, 'service_id' => $service->id, 'primary_staff_profile_id' => ($staff ?? $path['staff'])->id, 'sequence' => 1, 'name' => 'Signature cut', 'price_minor' => 3000, 'currency_code' => 'INR', 'bookable_minutes' => 30, 'configuration_snapshot' => []]);
    AppointmentSegment::query()->create(['business_id' => $path['business']->id, 'appointment_id' => $record->id, 'appointment_service_line_id' => $line->id, 'sequence' => 1, 'kind' => 'active', 'staff_profile_id' => ($staff ?? $path['staff'])->id, 'starts_at_utc' => $start, 'ends_at_utc' => $start->addMinutes(30), 'occupies_staff' => true, 'time_zone' => $path['location']->time_zone, 'local_starts_at' => $record->local_starts_at, 'local_ends_at' => $record->local_ends_at]);

    return $record;
}

it('renders a real operational dashboard with next actions and local hours', function () {
    CarbonImmutable::setTestNow('2026-10-02 05:00:00 UTC');
    $p = dailyWorkspaceFixture();
    $a = dailyWorkspaceAppointment($p, 'pending_confirmation');
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('workspace.scope', 'location')->where('workspace.phase', 'open')->where('workspace.appointments.0.action.status', 'confirmed')->where('workspace.appointments.0.id', $a->public_id)->where('workspace.team.0.windows.0.opens_at', '09:00')->where('todayMetrics.cards.staff_available.value', 1)->missing('calendar.events'));
});

it('keeps a professional within their schedule and removes peer and financial data', function () {
    CarbonImmutable::setTestNow('2026-10-02 05:00:00 UTC');
    $p = dailyWorkspaceFixture(StarterRole::BarberStylist);
    $own = dailyWorkspaceAppointment($p);
    $peer = StaffProfile::factory()->create(['business_id' => $p['business']->id]);
    dailyWorkspaceAppointment($p, staff: $peer);
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.scope', 'personal')->has('workspace.appointments', 1)->where('workspace.appointments.0.id', $own->public_id)->where('workspace.team.0.id', $p['staff']->public_id)->where('workspace.queue', [])->where('workspace.checkouts', [])->where('todayMetrics.cards.collected_revenue_minor.visible', false)->where('todayMetrics.cards.collected_revenue_minor.value', null)->where('permissions.walkIns', false));
});

it('fails closed for own-calendar permission without a linked staff profile', function () {
    $p = dailyWorkspaceFixture(StarterRole::BarberStylist);
    dailyWorkspaceAppointment($p);
    $p['staff']->update(['membership_id' => null]);
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.appointments', [])->where('workspace.team', [])->where('todayMetrics.cards.appointments.visible', false)->where('permissions.calendar', false));
});

it('gives accountants financial context without schedules or client contacts', function () {
    $p = dailyWorkspaceFixture(StarterRole::Accountant);
    dailyWorkspaceAppointment($p);
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.scope', 'finance')->where('workspace.appointments', [])->where('workspace.team', [])->where('workspace.checkouts', [])->where('todayMetrics.cards.collected_revenue_minor.visible', true)->where('permissions.createAppointment', false));
});

it('checks custom permissions separately for contact notes and appointment actions', function () {
    $p = dailyWorkspaceFixture(StarterRole::Receptionist);
    dailyWorkspaceAppointment($p);
    app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p) {
        $p['membership']->syncRoles([]);
        $p['membership']->syncPermissions([PermissionName::CalendarViewAll->value]);
    });
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.appointments.0.clientMobile', null)->where('workspace.appointments.0.clientEmail', null)->where('workspace.appointments.0.internalNotes', null)->where('workspace.appointments.0.action', null)->where('permissions.checkout', false)->where('permissions.setup', false)->where('todayMetrics.cards.walk_ins_waiting.visible', false));
    $this->get(route('business.calendar', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('calendar.events.0.clientMobile', null)->where('calendar.events.0.clientEmail', null)->where('calendar.events.0.internalNotes', null)->where('permissions.notes', false));
});

it('rejects unassigned and foreign locations and malformed dates', function () {
    $p = dailyWorkspaceFixture(StarterRole::Manager);
    $other = Location::factory()->create(['business_id' => $p['business']->id]);
    $foreign = Location::factory()->create();
    foreach ([$other, $foreign] as $l) {
        $this->actingAs($p['user'])->get(route('business.dashboard', ['business' => $p['business'], 'location' => $l->public_id]))->assertNotFound();
    }
    $this->actingAs($p['user'])->getJson(route('business.dashboard', ['business' => $p['business'], 'date' => 'definitely-not-a-date']))->assertUnprocessable();
});

it('uses the location day and handles closing boundaries without device time', function () {
    CarbonImmutable::setTestNow('2026-10-01 18:40:00 UTC');
    $p = dailyWorkspaceFixture();
    dailyWorkspaceAppointment($p, start: CarbonImmutable::parse('2026-10-01 18:35:00 UTC'));
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-02')->where('workspace.phase', 'before_open')->has('workspace.appointments', 1));
    CarbonImmutable::setTestNow('2026-10-02 13:30:00 UTC');
    $this->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.phase', 'after_close'));
});

it('does not count active staff on full-day leave as scheduled', function () {
    CarbonImmutable::setTestNow('2026-10-02 05:00:00 UTC');
    $p = dailyWorkspaceFixture();
    StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'staff_profile_id' => $p['staff']->id, 'location_id' => $p['location']->id, 'kind' => 'sick_leave', 'starts_on' => '2026-10-02', 'ends_on' => '2026-10-02', 'sequence' => 2]);
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('todayMetrics.cards.staff_available.value', 0)->where('workspace.team.0.windows', []));
});

it('bounds appointment payloads while keeping status totals exact', function () {
    $p = dailyWorkspaceFixture();
    for ($i = 0; $i < 105; $i++) {
        dailyWorkspaceAppointment($p);
    }
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('workspace.appointments', 100)->where('workspace.truncated', true)->where('todayMetrics.cards.appointments.value', 105));
});

it('transitions appointments idempotently and preserves stale version protection', function () {
    $p = dailyWorkspaceFixture(StarterRole::Receptionist);
    $a = dailyWorkspaceAppointment($p, 'pending_confirmation');
    $url = route('business.appointments.status', [$p['business'], $a]);
    $payload = ['status' => 'confirmed', 'version' => 1, 'idempotency_key' => 'dashboard-confirm-once'];
    $this->actingAs($p['user'])->patch($url, $payload)->assertRedirect();
    $this->patch($url, $payload)->assertRedirect();
    expect($a->fresh()->status)->toBe('confirmed')->and($a->fresh()->version)->toBe(2);
    $this->patchJson($url, ['status' => 'arrived', 'version' => 1, 'idempotency_key' => 'stale-dashboard-arrival'])->assertUnprocessable()->assertJsonPath('code', 'STALE_APPOINTMENT');
    expect(AppointmentLifecycleService::primaryActionFor('completed'))->toBeNull();
});

it('retains active work before finished history when the dashboard slice fills', function () {
    $p = dailyWorkspaceFixture();
    for ($i = 0; $i < 100; $i++) {
        dailyWorkspaceAppointment($p, 'completed', start: CarbonImmutable::now()->subMinutes(60));
    }
    $active = dailyWorkspaceAppointment($p, 'pending_confirmation');
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('workspace.appointments', 100)->where('workspace.truncated', true)->where('todayMetrics.cards.appointments.value', 101)->where('workspace.appointments.99.id', $active->public_id));
});

it('loads team availability without adding queries per staff member', function () {
    $p = dailyWorkspaceFixture();
    $measure = function () use ($p): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TodayDashboardService::class)->forLocation($p['business'], $p['membership'], $p['location'], CarbonImmutable::now($p['location']->time_zone));
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p, $measure) {
        $measure(); // Warm authorization relations before comparing staff scale.
        $one = $measure();
        for ($i = 0; $i < 20; $i++) {
            $staff = StaffProfile::factory()->create(['business_id' => $p['business']->id, 'status' => 'active']);
            $staff->locations()->attach($p['location'], ['business_id' => $p['business']->id]);
            StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'staff_profile_id' => $staff->id, 'kind' => 'working', 'day_of_week' => CarbonImmutable::now($p['location']->time_zone)->dayOfWeekIso, 'starts_at' => '09:00', 'ends_at' => '17:00', 'sequence' => 1]);
        }
        expect($measure())->toBe($one);
    });
});

it('deep links cancelled visits with their status included in the calendar filter', function () {
    $p = dailyWorkspaceFixture();
    $a = dailyWorkspaceAppointment($p, 'cancelled_by_shop');
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.appointments.0.action', null)->where('workspace.appointments.0.href', fn ($href) => str_contains($href, 'appointment='.$a->public_id) && str_contains($href, 'status[]=cancelled_by_shop')));
});

it('links an older unresolved checkout and denies checkout outside assigned locations', function () {
    $p = dailyWorkspaceFixture(StarterRole::Receptionist);
    $old = dailyWorkspaceAppointment($p, 'completed', start: CarbonImmutable::now()->subDays(40));
    for ($i = 0; $i < 31; $i++) {
        dailyWorkspaceAppointment($p, 'completed');
    }
    $this->actingAs($p['user'])->get(route('business.checkout.index', ['business' => $p['business'], 'appointment' => $old->public_id]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedAppointment', $old->public_id)->where('appointments.0.public_id', $old->public_id));
    $denied = $p;
    $denied['location'] = Location::factory()->create(['business_id' => $p['business']->id]);
    $a = dailyWorkspaceAppointment($denied, 'completed');
    $this->get(route('business.checkout.index', ['business' => $p['business'], 'appointment' => $a->public_id]))->assertNotFound();
    $this->postJson(route('business.checkout.open', [$p['business'], $a]), ['lines' => []])->assertForbidden();
});

it('hides settled checkout actions and keeps unresolved checkout counts location scoped', function () {
    $p = dailyWorkspaceFixture();
    $settled = dailyWorkspaceAppointment($p, 'completed');
    dailyWorkspaceAppointment($p, 'completed');
    Sale::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'appointment_id' => $settled->id, 'status' => 'completed', 'currency_code' => 'INR', 'total_minor' => 3000, 'paid_minor' => 3000, 'balance_minor' => 0, 'calculation_snapshot' => []]);
    $this->actingAs($p['user'])->get(route('business.dashboard', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.checkoutCount', 1)->where('workspace.appointments.0.checkoutHref', null));
});
