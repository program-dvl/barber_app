<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Services\SchedulingRecordLookup;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentOperationsController extends Controller
{
    public function store(Request $request, Business $business, BookingCommitCommand $bookings, TenantContext $context, AuditWriter $audit): RedirectResponse
    {
        $membership = $context->membership();
        abort_unless($membership && ($membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web') || $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web')), 403);
        $data = $request->validate([
            'location' => ['required', 'string'], 'starts_at' => ['required', 'date'], 'source' => ['required', 'in:phone,reception,recurring,consultation'],
            'client_name' => ['nullable', 'string', 'max:255'], 'client_mobile' => ['nullable', 'string', 'max:32'], 'internal_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:12'], 'lines.*.service' => ['required', 'string'],
            'lines.*.staff' => ['nullable', 'string'], 'lines.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:720'],
            'idempotency_key' => ['required', 'string', 'max:128'], 'override_rule_codes' => ['array'], 'override_rule_codes.*' => ['in:NOTICE_WINDOW,ADVANCE_WINDOW'],
            'override_reason' => ['nullable', 'string', 'max:1000'], 'override_confirmed' => ['nullable', 'boolean'],
        ]);
        $bookingRequest = $this->bookingRequest($request, $business, $data, $context);
        $appointment = $bookings->commit($bookingRequest, $data['idempotency_key']);
        $audit->write('appointment.created', $business, $request->user(), $appointment, $data['override_reason'] ?? null, [], [
            'public_id' => $appointment->public_id, 'starts_at_utc' => $appointment->starts_at_utc->toIso8601String(), 'source' => $appointment->source,
        ], ['override_rule_codes' => $bookingRequest->overrideRuleCodes], 'calendar');

        return back()->with('status', 'Appointment created.');
    }

    public function transition(Request $request, Business $business, string $appointment, SchedulingRecordLookup $records, AppointmentLifecycleCommand $lifecycle, AuditWriter $audit): RedirectResponse
    {
        $record = $records->appointment($business->id, $appointment);
        $this->authorize('update', $record);
        $data = $request->validate([
            'status' => ['required', 'string'], 'version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'string', 'max:128'],
            'confirmed' => ['nullable', 'boolean'],
        ]);
        if (in_array($data['status'], ['cancelled_by_client', 'cancelled_by_shop', 'no_show'], true)) {
            abort_unless($request->boolean('confirmed'), 422, 'Confirm this destructive change.');
        }
        $updated = $lifecycle->transition($record, $data['status'], $data['idempotency_key'], $data['version'], 'calendar', 'user', $request->user()->id, $data['reason'] ?? null);
        $audit->write('appointment.status_changed', $business, $request->user(), $updated, $data['reason'] ?? null, ['status' => $record->status], ['status' => $updated->status], [], 'calendar');

        return back()->with('status', 'Appointment status updated.');
    }

    public function replace(Request $request, Business $business, string $appointment, SchedulingRecordLookup $records, AppointmentLifecycleCommand $lifecycle, TenantContext $context, AuditWriter $audit): RedirectResponse
    {
        $record = $records->appointment($business->id, $appointment);
        $this->authorize('update', $record);
        $data = $request->validate([
            'kind' => ['required', 'in:reschedule,resize,reassign,services_changed'], 'location' => ['required', 'string'], 'starts_at' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1', 'max:12'], 'lines.*.service' => ['required', 'string'], 'lines.*.staff' => ['nullable', 'string'],
            'lines.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:720'], 'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['accepted'], 'idempotency_key' => ['required', 'string', 'max:128'],
            'override_rule_codes' => ['array'], 'override_rule_codes.*' => ['in:NOTICE_WINDOW,ADVANCE_WINDOW'], 'override_reason' => ['nullable', 'string', 'max:1000'], 'override_confirmed' => ['nullable', 'boolean'],
            'client_name' => ['nullable', 'string', 'max:255'], 'client_mobile' => ['nullable', 'string', 'max:32'], 'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['source'] = 'reception';
        $bookingRequest = $this->bookingRequest($request, $business, $data, $context);
        $replacement = $lifecycle->replace($record, $bookingRequest, $data['kind'], $data['idempotency_key'], $data['version'], $data['reason']);
        $audit->write('appointment.'.$data['kind'], $business, $request->user(), $replacement, $data['reason'], ['public_id' => $record->public_id], ['public_id' => $replacement->public_id], [], 'calendar');

        return back()->with('status', 'Appointment updated and the previous schedule was preserved in history.');
    }

    public function copy(Request $request, Business $business, string $appointment, SchedulingRecordLookup $records, BookingCommitCommand $bookings, AuditWriter $audit): RedirectResponse
    {
        $record = $records->appointment($business->id, $appointment);
        $this->authorize('update', $record);
        $data = $request->validate([
            'starts_at' => ['required', 'date'], 'kind' => ['required', 'in:duplicate,rebook'], 'confirmed' => ['accepted'], 'idempotency_key' => ['required', 'string', 'max:128'],
        ]);
        $record->load('serviceLines.segments');
        $lines = $record->serviceLines->map(fn ($line) => new BookingLineRequest(
            $line->service_id,
            $line->primary_staff_profile_id,
            $line->segments->mapWithKeys(fn ($segment) => [$segment->sequence => $segment->staff_profile_id])->all(),
            false,
        ))->all();
        $bookingRequest = new BookingRequest(
            $business->id, $record->location_id, CarbonImmutable::parse($data['starts_at'], $record->time_zone)->utc(), $lines,
            'reception', 'existing', CarbonImmutable::now()->utc(), null, 'user', $request->user()->id,
            $record->client_name, $record->client_mobile, $record->internal_notes,
        );
        $copy = $bookings->commit($bookingRequest, $data['idempotency_key']);
        $audit->write('appointment.'.$data['kind'], $business, $request->user(), $copy, null, ['copied_from' => $record->public_id], ['public_id' => $copy->public_id], [], 'calendar');

        return back()->with('status', ucfirst($data['kind']).' appointment created.');
    }

    public function notes(Request $request, Business $business, string $appointment, SchedulingRecordLookup $records, AppointmentLifecycleCommand $lifecycle, AuditWriter $audit): RedirectResponse
    {
        $record = $records->appointment($business->id, $appointment);
        $this->authorize('update', $record);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:5000'], 'version' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:128']]);
        $updated = $lifecycle->updateNotes($record, $data['notes'] ?? null, $data['idempotency_key'], $data['version'], 'calendar', 'user', $request->user()->id);
        $audit->write('appointment.notes_changed', $business, $request->user(), $updated, null, ['present' => $record->internal_notes !== null], ['present' => $updated->internal_notes !== null], [], 'calendar');

        return back()->with('status', 'Internal note updated.');
    }

    /** @param array<string, mixed> $data */
    private function bookingRequest(Request $request, Business $business, array $data, TenantContext $context): BookingRequest
    {
        $membership = $context->membership();
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $data['location'])->firstOrFail();
        abort_unless($membership->hasRole('owner', 'web') || $membership->locations()->whereKey($location->id)->exists(), 403);
        $servicePublicIds = collect($data['lines'])->pluck('service')->all();
        $services = Service::query()->where('business_id', $business->id)->whereIn('public_id', $servicePublicIds)->get()->keyBy('public_id');
        abort_unless($services->count() === count(array_unique($servicePublicIds)), 422);
        $staffPublicIds = collect($data['lines'])->pluck('staff')->filter()->all();
        $staff = StaffProfile::query()->where('business_id', $business->id)->whereIn('public_id', $staffPublicIds)->get()->keyBy('public_id');
        abort_unless($staff->count() === count(array_unique($staffPublicIds)), 422);
        $ownOnly = ! $membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web');
        if ($ownOnly) {
            abort_unless($membership->staffProfile, 403);
        }
        $lines = collect($data['lines'])->map(function (array $line) use ($services, $staff, $ownOnly, $membership): BookingLineRequest {
            $staffId = $ownOnly ? $membership->staffProfile->id : ($line['staff'] ? $staff[$line['staff']]->id : null);

            return new BookingLineRequest($services[$line['service']]->id, $staffId, [], $staffId === null, $line['duration_minutes'] ?? null);
        })->all();
        $overrideCodes = array_values(array_unique($data['override_rule_codes'] ?? []));
        if ($overrideCodes !== []) {
            abort_unless($membership->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web') && $request->boolean('override_confirmed') && trim((string) ($data['override_reason'] ?? '')) !== '', 403);
        }

        return new BookingRequest(
            $business->id, $location->id, CarbonImmutable::parse($data['starts_at'], $location->time_zone)->utc(), $lines,
            $data['source'], 'existing', CarbonImmutable::now()->utc(), null, 'user', $request->user()->id,
            $data['client_name'] ?? null, $data['client_mobile'] ?? null, $data['internal_notes'] ?? null,
            $overrideCodes, $data['override_reason'] ?? null,
        );
    }
}
