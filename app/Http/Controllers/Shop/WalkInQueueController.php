<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Domain\SchedulingOperations\Services\AtomicBookingService;
use App\Domain\SchedulingOperations\Services\BookingRuleEngine;
use App\Domain\SchedulingOperations\Services\CalendarWorkspaceQuery;
use App\Domain\SchedulingOperations\Services\SchedulingRecordLookup;
use App\Domain\SchedulingOperations\Services\WalkInQueueService;
use App\Domain\SchedulingOperations\Services\WalkInWorkspaceQuery;
use App\Http\Controllers\Controller;
use App\Rules\E164Phone;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WalkInQueueController extends Controller
{
    public function index(Request $request, Business $business, TenantContext $context, CalendarWorkspaceQuery $calendar, WalkInWorkspaceQuery $workspace): Response
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::WalkInsManage->value, 'web'), 403);
        $locations = Location::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when(! $membership->hasRole('owner', 'web'), fn ($query) => $query->whereIn('id', $membership->locations()->pluck('locations.id')))
            ->orderBy('name')->get();
        abort_if($locations->isEmpty(), 403);
        $location = $request->filled('location') ? $locations->firstWhere('public_id', $request->string('location')->toString()) : $locations->first();
        abort_unless($location, 404);
        $entries = WalkInEntry::query()
            ->where('business_id', $business->id)->where('location_id', $location->id)
            ->whereIn('status', ['waiting', 'notified', 'assigned', 'in_service'])
            ->with(['client', 'service', 'assignedStaff', 'preferredStaff', 'appointment.segments', 'history' => fn ($q) => $q->reorder('id', 'desc')->limit(6)])
            ->orderByRaw("CASE status WHEN 'in_service' THEN 1 ELSE 0 END")
            ->orderBy('queue_position')->get();
        $services = Service::query()->where('business_id', $business->id)->where('is_active', true)->where('kind', 'service')->whereHas('locations', fn ($q) => $q->whereKey($location->id)->where('location_service.is_eligible', true))->with(['staffAssignments', 'locations', 'segments'])->orderBy('name')->get();
        $staff = StaffProfile::query()->where('business_id', $business->id)->where('status', 'active')
            ->whereHas('locations', fn ($query) => $query->whereKey($location->id))->with(['locations', 'availabilityRules'])->orderBy('display_name')->get();

        $now = CarbonImmutable::now()->utc();
        $interval = max(1, (int) ($business->appointment_interval_minutes ?: 15));
        $board = $workspace->build($location, $staff, $services, $entries, $calendar->build($location, $staff, $now, 1), $now, $interval);
        $contact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $notes = $membership->hasPermissionTo(PermissionName::ClientNotesManage->value, 'web');
        $recent = WalkInEntry::query()->where('business_id', $business->id)->where('location_id', $location->id)
            ->whereIn('status', ['completed', 'left'])->where('updated_at', '>=', $now->setTimezone($location->time_zone)->startOfDay()->utc())
            ->with(['client', 'service', 'assignedStaff', 'appointment'])->latest('updated_at')->limit(8)->get();
        $serialize = function ($entry) use ($board, $contact, $notes, $request, $membership) {
            $forecast = $board['forecast'][$entry->public_id] ?? [];

            return [
                'public_id' => $entry->public_id, 'client_name' => $entry->client_name,
                'client_mobile' => $contact ? $entry->client_mobile : null,
                'client_public_id' => $membership->hasPermissionTo(PermissionName::ClientView->value, 'web') ? $entry->client?->public_id : null,
                'notes' => $notes ? $entry->notes : null, 'status' => $entry->status,
                'queue_position' => $entry->queue_position, 'arrived_at' => $entry->arrived_at->toIso8601String(),
                'estimated_at' => $forecast['estimated_at'] ?? null, 'suggested_staff_id' => $forecast['suggested_staff_id'] ?? null,
                'choices' => $forecast['choices'] ?? [], 'original_estimated_at' => $entry->estimated_service_at?->toIso8601String(),
                'service_name' => $entry->service?->name ?? 'Service unavailable',
                'duration_minutes' => $entry->service?->duration_minutes,
                'service_id' => $entry->service?->public_id,
                'preferred_staff_id' => $entry->preferredStaff?->public_id, 'preferred_staff_name' => $entry->preferredStaff?->display_name,
                'assigned_staff_id' => $entry->assignedStaff?->public_id, 'assigned_staff_name' => $entry->assignedStaff?->display_name,
                'service_started_at' => $entry->service_started_at?->toIso8601String(),
                'service_ends_at' => $entry->appointment?->ends_at_utc?->toIso8601String(),
                'actual_wait_minutes' => $entry->actual_wait_minutes, 'version' => $entry->version,
                'appointment_id' => $entry->appointment?->public_id, 'appointment_version' => $entry->appointment?->version,
                'can_complete' => $entry->appointment && $entry->appointment->status === 'in_service' && $request->user()->can('update', $entry->appointment),
                'history' => $entry->relationLoaded('history') ? $entry->history->sortByDesc('id')->take(6)->map(fn ($h) => [
                    'action' => $h->action, 'reason' => $notes ? $h->reason : null, 'at' => $h->occurred_at->toIso8601String(),
                ])->values()->all() : [],
            ];
        };

        $clientPrefill = null;
        if ($request->filled('client')) {
            $client = Client::query()->where('business_id', $business->id)->where('status', 'active')->where('public_id', $request->string('client')->toString())->firstOrFail();
            $this->authorize('view', $client);
            $clientPrefill = ['public_id' => $client->public_id, 'name' => $client->name, 'mobile' => $contact ? $client->mobile : null, 'email' => $contact ? $client->email : null];
        }

        return Inertia::render('Operations/WalkInQueue', [
            'businessLabel' => $business->name, 'clientPrefill' => $clientPrefill,
            'location' => $location->only(['public_id', 'name', 'time_zone']),
            'locations' => $locations->map->only(['public_id', 'name']),
            'services' => $services->map->only(['public_id', 'name', 'duration_minutes']),
            'staff' => $board['team'], 'entries' => $entries->map($serialize), 'recent' => $recent->map($serialize),
            'updatedAt' => $board['updated_at'], 'bookingIntervalMinutes' => $interval,
            'canReorder' => $membership->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web'),
            'permissions' => ['contact' => $contact, 'notes' => $notes,
                'clients' => $membership->hasPermissionTo(PermissionName::ClientView->value, 'web'),
                'calendar' => $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web'),
                'checkout' => $membership->hasPermissionTo(PermissionName::CheckoutManage->value, 'web')],
        ]);
    }

    /** Full scheduling validation is performed on demand, rather than per row per refresh. */
    public function readiness(Request $request, Business $business, string $walkIn, SchedulingRecordLookup $records, BookingRuleEngine $rules, WalkInWorkspaceQuery $workspace)
    {
        $entry = $records->walkIn($business->id, $walkIn);
        $this->authorize('manage', $entry);
        $data = $request->validate(['staff' => ['required', 'string']]);
        $staff = StaffProfile::query()->where('business_id', $business->id)->where('public_id', $data['staff'])->firstOrFail();
        $location = Location::query()->where('business_id', $business->id)->findOrFail($entry->location_id);
        $now = CarbonImmutable::now()->utc();
        $start = $workspace->align($now, $location->time_zone, max(1, (int) ($business->appointment_interval_minutes ?: 15)));
        try {
            if ($entry->appointment_id) {
                throw new BookingRuleViolation('LINKED_APPOINTMENT', 'This walk-in has a Calendar visit. Start or change that visit in Calendar.');
            }
            if (! in_array($entry->status, ['waiting', 'assigned', 'notified'], true)) {
                throw new BookingRuleViolation('STALE_QUEUE', 'This client is no longer waiting. Refresh the queue.');
            }
            if (Appointment::query()->where('business_id', $business->id)->where('status', 'in_service')->where('ends_at_utc', '<=', $now)
                ->whereHas('segments', fn ($q) => $q->where('staff_profile_id', $staff->id)->where('occupies_staff', true))->exists()) {
                throw new BookingRuleViolation('SERVICE_RUNNING_OVER', $staff->display_name.' is still serving a client past the planned finish. Complete or adjust that visit first.');
            }
            $plan = $rules->plan(new BookingRequest($business->id, $location->id, $start, [new BookingLineRequest($entry->service_id, $staff->id, [], false)], 'walk_in', 'existing', $now), excludeAppointmentId: $entry->appointment_id);

            return response()->json(['ready' => true, 'starts_at' => $start->toIso8601String(), 'ends_at' => $plan->endsAtUtc->toIso8601String(), 'message' => 'Service fits the schedule. Staff and resources will be checked again when you start.']);
        } catch (BookingRuleViolation $error) {
            $message = $error->getMessage();
            if ($error->ruleCode === 'STAFF_UNAVAILABLE') {
                $day = app(CalendarWorkspaceQuery::class)->build($location, collect([$staff]), $now, 1)['days'][0]['staff'][0];
                $current = collect([...$day['unavailable'], ...$day['busy']])->first(fn ($r) => CarbonImmutable::parse($r['startsAt'])->lte($start) && CarbonImmutable::parse($r['endsAt'])->gt($start));
                $next = collect($day['busy'])->filter(fn ($r) => CarbonImmutable::parse($r['startsAt'])->gt($start))->sortBy('startsAt')->first();
                if ($current) {
                    $from = CarbonImmutable::parse($current['startsAt'])->setTimezone($location->time_zone)->format('g:i A');
                    $until = CarbonImmutable::parse($current['endsAt'])->setTimezone($location->time_zone)->format('g:i A');
                    $message = ($current['kind'] ?? null) === 'appointment'
                        ? $staff->display_name.' has a booking from '.$from.' to '.$until.'. This service does not fit before it. Choose another staff member or wait for their next opening.'
                        : $staff->display_name.' is unavailable from '.$from.' to '.$until.' ('.strtolower($current['label']).'). Choose another staff member or wait for their next opening.';
                } elseif ($next) {
                    $message = $staff->display_name.' has reserved time at '.CarbonImmutable::parse($next['startsAt'])->setTimezone($location->time_zone)->format('g:i A').'. This service does not fit the available gap. Choose another staff member.';
                } else {
                    $message = $staff->display_name.' cannot fit this service within their working hours. Choose another staff member or review the schedule.';
                }
            }

            return response()->json(['ready' => false, 'message' => $message, 'code' => $error->ruleCode]);
        } catch (ValidationException) {
            return response()->json(['ready' => false, 'message' => 'This staff member is not eligible for this service at this location.']);
        }
    }

    public function searchClients(Request $request, Business $business, TenantContext $context)
    {
        abort_unless($context->membership()?->hasPermissionTo(PermissionName::WalkInsManage->value, 'web'), 403);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $contact = $context->membership()->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $term = str_replace(['%', '_'], '', mb_strtolower(trim($data['q'])));
        $phone = preg_replace('/[^0-9+]/', '', $term);
        $clients = Client::query()->where('business_id', $business->id)->where('status', 'active')->whereNotNull('mobile')
            ->where(function ($query) use ($term, $phone, $contact): void {
                $query->whereRaw('lower(name) like ?', ["%{$term}%"]);
                if ($contact) {
                    $query->orWhere('normalized_email', 'like', "%{$term}%");
                    if (strlen($phone) >= 2) {
                        $query->orWhere('normalized_mobile', 'like', "%{$phone}%");
                    }
                }
            })->orderBy('name')->limit(8)->get(['public_id', 'name', 'mobile', 'email'])
            ->map(fn ($client) => ['public_id' => $client->public_id, 'name' => $client->name,
                'mobile' => $contact ? $client->mobile : null, 'email' => $contact ? $client->email : null]);

        return response()->json(['clients' => $clients]);
    }

    public function store(Request $request, Business $business, WalkInQueueService $queue, ClientIdentityService $clients, TenantContext $context, AuditWriter $audit): RedirectResponse
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::WalkInsManage->value, 'web'), 403);
        $data = $request->validate([
            'location' => ['required', 'string'], 'service' => ['required', 'string'], 'preferred_staff' => ['nullable', 'string'],
            'client_mode' => ['required', 'in:existing,new'], 'client' => ['nullable', 'string'],
            'client_name' => ['required_if:client_mode,new', 'nullable', 'string', 'max:255'], 'client_mobile' => ['required_if:client_mode,new', 'nullable', 'string', 'max:32', new E164Phone],
            'client_email' => ['nullable', 'email', 'max:255'],
            'arrived_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);
        abort_if(filled($data['notes'] ?? null) && ! $membership->hasPermissionTo(PermissionName::ClientNotesManage->value, 'web'), 403);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $data['location'])->firstOrFail();
        abort_unless($membership->hasRole('owner', 'web') || $membership->locations()->whereKey($location->id)->exists(), 403);
        $service = Service::query()->where('business_id', $business->id)->where('public_id', $data['service'])->firstOrFail();
        $staff = isset($data['preferred_staff']) ? StaffProfile::query()->where('business_id', $business->id)->where('public_id', $data['preferred_staff'])->firstOrFail() : null;
        $entry = DB::transaction(function () use ($data, $business, $location, $service, $staff, $queue, $clients, $request) {
            $key = null;
            if (filled($data['idempotency_key'] ?? null)) {
                $hash = hash('sha256', json_encode([$data, $request->user()->id], JSON_THROW_ON_ERROR));
                $key = app(AtomicBookingService::class)->claimCommandKey($business->id, 'operation', 'queue-add:'.$data['idempotency_key'], $hash);
                if ($key->result_id) {
                    return WalkInEntry::query()->where('business_id', $business->id)->findOrFail($key->result_id);
                }
            }
            if ($data['client_mode'] === 'existing') {
                $client = Client::query()->where('business_id', $business->id)->where('status', 'active')
                    ->where('public_id', $data['client'] ?? '')->firstOrFail();
                if (! E164Phone::isValid(E164Phone::normalize($client->mobile))) {
                    throw ValidationException::withMessages(['client' => 'This client needs a valid mobile number. Update their profile before check-in.']);
                }
            } else {
                $client = $clients->createManual($business, [
                    'name' => $data['client_name'], 'mobile' => $data['client_mobile'],
                    'email' => $data['client_email'] ?? null, 'referral_source' => 'walk_in',
                ])['client'];
            }

            $entry = $queue->add(
                $business->id, $location->id, $service->id, $client->name, E164Phone::normalize($client->mobile), $staff?->id,
                CarbonImmutable::parse($data['arrived_at'], $location->time_zone)->utc(), $data['notes'] ?? null, 'reception', 'user', $request->user()->id,
                $client->id, $client->email,
            );
            if ($key) {
                DB::table('booking_command_keys')->where('id', $key->id)->update(['result_type' => 'walk_in', 'result_id' => $entry->id, 'updated_at' => now()]);
            }

            return $entry;
        }, 3);
        $audit->write('walk_in.created', $business, $request->user(), $entry, null, [], ['public_id' => $entry->public_id, 'queue_position' => $entry->queue_position], [], 'queue');

        return back()->with('status', 'Walk-in added to the queue.');
    }

    public function assign(Request $request, Business $business, string $walkIn, SchedulingRecordLookup $records, WalkInQueueService $queue, AuditWriter $audit): RedirectResponse
    {
        $entry = $records->walkIn($business->id, $walkIn);
        $this->authorize('manage', $entry);
        $data = $request->validate(['staff' => ['required', 'string'], 'version' => ['required', 'integer'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $staff = StaffProfile::query()->where('business_id', $business->id)->where('public_id', $data['staff'])->firstOrFail();
        $updated = $queue->assign($entry, $staff->id, $data['version'], 'reception', 'user', $request->user()->id, $data['reason'] ?? null);
        $audit->write('walk_in.assigned', $business, $request->user(), $updated, $data['reason'] ?? null, [], ['staff_public_id' => $staff->public_id], [], 'queue');

        return back()->with('status', 'Staff assigned.');
    }

    public function notify(Request $request, Business $business, string $walkIn, SchedulingRecordLookup $records, WalkInQueueService $queue): RedirectResponse
    {
        $entry = $records->walkIn($business->id, $walkIn);
        $this->authorize('manage', $entry);
        $data = $request->validate(['version' => ['required', 'integer']]);
        $queue->notify($entry, $data['version'], 'reception', 'user', $request->user()->id);

        return back()->with('status', 'Client notification requested.');
    }

    public function reorder(Request $request, Business $business, WalkInQueueService $queue, TenantContext $context, AuditWriter $audit): RedirectResponse
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web') && $membership->hasPermissionTo(PermissionName::WalkInsManage->value, 'web'), 403);
        $data = $request->validate(['location' => ['required', 'string'], 'entries' => ['required', 'array'], 'entries.*' => ['string'], 'reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['accepted']]);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $data['location'])->firstOrFail();
        abort_unless($membership->hasRole('owner', 'web') || $membership->locations()->whereKey($location->id)->exists(), 403);
        $queue->reorder($business->id, $location->id, $data['entries'], $data['reason'], 'reception', 'user', $request->user()->id);
        $audit->write('walk_in.queue_reordered', $business, $request->user(), null, $data['reason'], [], ['ordered_public_ids' => $data['entries']], [], 'queue');

        return back()->with('status', 'Queue order updated.');
    }

    public function start(Request $request, Business $business, string $walkIn, SchedulingRecordLookup $records, WalkInQueueService $queue, AuditWriter $audit): RedirectResponse
    {
        $entry = $records->walkIn($business->id, $walkIn);
        $this->authorize('manage', $entry);
        $data = $request->validate(['starts_at' => ['required', 'date'], 'staff' => ['nullable', 'string'], 'version' => ['required', 'integer'], 'idempotency_key' => ['required', 'string', 'max:100']]);
        $staff = isset($data['staff']) ? StaffProfile::query()->where('business_id', $business->id)->where('public_id', $data['staff'])->firstOrFail() : null;
        $location = Location::query()->where('business_id', $business->id)->findOrFail($entry->location_id);
        $appointment = $queue->startService($entry, CarbonImmutable::parse($data['starts_at'], $location->time_zone)->utc(), $data['idempotency_key'], $data['version'], $staff?->id, 'reception', 'user', $request->user()->id);
        $audit->write('walk_in.service_started', $business, $request->user(), $entry->fresh(), null, [], ['appointment_public_id' => $appointment->public_id], [], 'queue');

        return back()->with('status', 'Service started.');
    }

    public function leave(Request $request, Business $business, string $walkIn, SchedulingRecordLookup $records, WalkInQueueService $queue, AuditWriter $audit): RedirectResponse
    {
        $entry = $records->walkIn($business->id, $walkIn);
        $this->authorize('manage', $entry);
        $data = $request->validate(['version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['accepted']]);
        $updated = $queue->markLeft($entry, $data['version'], $data['reason'], 'reception', 'user', $request->user()->id);
        $audit->write('walk_in.left', $business, $request->user(), $updated, $data['reason'], [], ['actual_wait_minutes' => $updated->actual_wait_minutes], [], 'queue');

        return back()->with('status', 'Client marked as left.');
    }
}
