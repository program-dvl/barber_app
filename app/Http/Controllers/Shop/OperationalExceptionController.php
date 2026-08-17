<?php

namespace App\Http\Controllers\Shop;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Services\OperationalExceptionService;
use App\Domain\SchedulingOperations\Services\SchedulingRecordLookup;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OperationalExceptionController extends Controller
{
    public function appointment(Request $request, Business $business, string $appointment, SchedulingRecordLookup $records, OperationalExceptionService $exceptions, AuditWriter $audit): RedirectResponse
    {
        $record = $records->appointment($business->id, $appointment);
        $this->authorize('update', $record);
        $data = $request->validate(['kind' => ['required', 'in:late_arrival,service_overrun,staff_unavailable'], 'reason' => ['required', 'string', 'max:1000'], 'projected_end' => ['nullable', 'date']]);
        $exception = $exceptions->recordAppointmentImpact($record, $data['kind'], $data['reason'], isset($data['projected_end']) ? CarbonImmutable::parse($data['projected_end'], $record->time_zone)->utc() : null, 'user', $request->user()->id);
        $audit->write('appointment.exception_recorded', $business, $request->user(), $exception, $data['reason'], [], ['kind' => $data['kind'], 'impact' => $exception->impact], [], 'calendar');

        return back()->with('status', 'Operational exception recorded with affected appointments.');
    }

    public function closure(Request $request, Business $business, OperationalExceptionService $exceptions, TenantContext $context, AuditWriter $audit): RedirectResponse
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web'), 403);
        $data = $request->validate(['location' => ['required', 'string'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['accepted']]);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $data['location'])->firstOrFail();
        $exception = $exceptions->unexpectedClosure($business->id, $location->id, CarbonImmutable::parse($data['starts_at'], $location->time_zone)->utc(), CarbonImmutable::parse($data['ends_at'], $location->time_zone)->utc(), $data['reason'], 'user', $request->user()->id);
        $audit->write('location.unexpected_closure', $business, $request->user(), $exception, $data['reason'], [], ['impact' => $exception->impact], [], 'calendar');

        return back()->with('status', 'Unexpected closure recorded. Review and contact every affected client.');
    }
}
