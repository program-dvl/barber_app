<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Domain\SchedulingOperations\Services\SchedulingRecordLookup;
use App\Domain\SchedulingOperations\Services\WalkInQueueService;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalkInQueueController extends Controller
{
    public function index(Request $request, Business $business, TenantContext $context): Response
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
            ->orderByRaw("CASE status WHEN 'in_service' THEN 1 ELSE 0 END")
            ->orderBy('queue_position')->get();
        $services = Service::query()->where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get();
        $staff = StaffProfile::query()->where('business_id', $business->id)->where('status', 'active')
            ->whereHas('locations', fn ($query) => $query->whereKey($location->id))->orderBy('display_name')->get();

        return Inertia::render('Operations/WalkInQueue', [
            'businessLabel' => $business->name,
            'location' => $location->only(['public_id', 'name', 'time_zone']),
            'locations' => $locations->map->only(['public_id', 'name']),
            'services' => $services->map->only(['public_id', 'name', 'duration_minutes']),
            'staff' => $staff->map->only(['public_id', 'display_name']),
            'entries' => $entries->map(fn ($entry) => [
                'public_id' => $entry->public_id, 'client_name' => $entry->client_name,
                'client_mobile' => $entry->client_mobile, 'status' => $entry->status,
                'queue_position' => $entry->queue_position, 'estimated_wait_minutes' => $entry->estimated_wait_minutes,
                'estimated_service_at' => $entry->estimated_service_at?->setTimezone($location->time_zone)->toIso8601String(),
                'arrived_at' => $entry->arrived_at->setTimezone($location->time_zone)->toIso8601String(),
                'service_id' => $services->firstWhere('id', $entry->service_id)?->public_id,
                'preferred_staff_id' => $staff->firstWhere('id', $entry->preferred_staff_profile_id)?->public_id,
                'assigned_staff_id' => $staff->firstWhere('id', $entry->assigned_staff_profile_id)?->public_id,
                'version' => $entry->version, 'appointment_created' => $entry->appointment_id !== null,
                'estimate_evidence' => $entry->estimate_evidence,
            ]),
            'bookingIntervalMinutes' => max(1, (int) ($business->appointment_interval_minutes ?: 15)),
            'canReorder' => $membership->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web'),
        ]);
    }

    public function store(Request $request, Business $business, WalkInQueueService $queue, TenantContext $context, AuditWriter $audit): RedirectResponse
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::WalkInsManage->value, 'web'), 403);
        $data = $request->validate([
            'location' => ['required', 'string'], 'service' => ['required', 'string'], 'preferred_staff' => ['nullable', 'string'],
            'client_name' => ['required', 'string', 'max:255'], 'client_mobile' => ['required', 'string', 'max:32'],
            'arrived_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $data['location'])->firstOrFail();
        abort_unless($membership->hasRole('owner', 'web') || $membership->locations()->whereKey($location->id)->exists(), 403);
        $service = Service::query()->where('business_id', $business->id)->where('public_id', $data['service'])->firstOrFail();
        $staff = isset($data['preferred_staff']) ? StaffProfile::query()->where('business_id', $business->id)->where('public_id', $data['preferred_staff'])->firstOrFail() : null;
        $entry = $queue->add(
            $business->id, $location->id, $service->id, $data['client_name'], $data['client_mobile'], $staff?->id,
            CarbonImmutable::parse($data['arrived_at'], $location->time_zone)->utc(), $data['notes'] ?? null, 'reception', 'user', $request->user()->id,
        );
        $audit->write('walk_in.created', $business, $request->user(), $entry, null, [], ['public_id' => $entry->public_id, 'queue_position' => $entry->queue_position], [], 'queue');

        return back()->with('status', 'Walk-in added with an evidence-based wait estimate.');
    }

    public function assign(Request $request, Business $business, string $walkIn, SchedulingRecordLookup $records, WalkInQueueService $queue, AuditWriter $audit): RedirectResponse
    {
        $entry = $records->walkIn($business->id, $walkIn);
        $this->authorize('manage', $entry);
        $data = $request->validate(['staff' => ['required', 'string'], 'version' => ['required', 'integer'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $staff = StaffProfile::query()->where('business_id', $business->id)->where('public_id', $data['staff'])->firstOrFail();
        $updated = $queue->assign($entry, $staff->id, $data['version'], 'reception', 'user', $request->user()->id, $data['reason'] ?? null);
        $audit->write('walk_in.assigned', $business, $request->user(), $updated, $data['reason'] ?? null, [], ['staff_public_id' => $staff->public_id], [], 'queue');

        return back()->with('status', 'Walk-in assigned. Capacity will be revalidated when service starts.');
    }

    public function notify(Request $request, Business $business, string $walkIn, SchedulingRecordLookup $records, WalkInQueueService $queue): RedirectResponse
    {
        $entry = $records->walkIn($business->id, $walkIn);
        $this->authorize('manage', $entry);
        $data = $request->validate(['version' => ['required', 'integer']]);
        $queue->notify($entry, $data['version'], 'reception', 'user', $request->user()->id);

        return back()->with('status', 'Turn notification queued.');
    }

    public function reorder(Request $request, Business $business, WalkInQueueService $queue, TenantContext $context, AuditWriter $audit): RedirectResponse
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web'), 403);
        $data = $request->validate(['location' => ['required', 'string'], 'entries' => ['required', 'array'], 'entries.*' => ['string'], 'reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['accepted']]);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $data['location'])->firstOrFail();
        $queue->reorder($business->id, $location->id, $data['entries'], $data['reason'], 'reception', 'user', $request->user()->id);
        $audit->write('walk_in.queue_reordered', $business, $request->user(), null, $data['reason'], [], ['ordered_public_ids' => $data['entries']], [], 'queue');

        return back()->with('status', 'Queue reordered with reason recorded.');
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

        return back()->with('status', 'Service started and future capacity remains protected.');
    }

    public function leave(Request $request, Business $business, string $walkIn, SchedulingRecordLookup $records, WalkInQueueService $queue, AuditWriter $audit): RedirectResponse
    {
        $entry = $records->walkIn($business->id, $walkIn);
        $this->authorize('manage', $entry);
        $data = $request->validate(['version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['accepted']]);
        $updated = $queue->markLeft($entry, $data['version'], $data['reason'], 'reception', 'user', $request->user()->id);
        $audit->write('walk_in.left', $business, $request->user(), $updated, $data['reason'], [], ['actual_wait_minutes' => $updated->actual_wait_minutes], [], 'queue');

        return back()->with('status', 'Walk-in removed from the active queue.');
    }
}
