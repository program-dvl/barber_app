<?php

namespace App\Http\Controllers\Shop;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Services\ScheduleBlockService;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleBlockController extends Controller
{
    public function __invoke(Request $request, Business $business, ScheduleBlockService $blocks, TenantContext $context, AuditWriter $audit): RedirectResponse
    {
        $membership = $context->membership();
        abort_unless($membership && ($membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web') || $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web')), 403);
        $data = $request->validate([
            'location' => ['required', 'string'], 'staff' => ['required', 'string'], 'kind' => ['required', 'in:personal_block,staff_break'],
            'label' => ['required', 'string', 'max:255'], 'reason' => ['required', 'string', 'max:1000'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'confirmed' => ['accepted'],
        ]);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $data['location'])->firstOrFail();
        $staff = StaffProfile::query()->where('business_id', $business->id)->where('public_id', $data['staff'])->firstOrFail();
        if (! $membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web')) {
            abort_unless($membership->staffProfile?->id === $staff->id, 403);
        }
        $block = $blocks->create($business->id, $location->id, $staff->id, $data['kind'], $data['label'], CarbonImmutable::parse($data['starts_at'], $location->time_zone)->utc(), CarbonImmutable::parse($data['ends_at'], $location->time_zone)->utc(), $data['reason'], 'user', $request->user()->id);
        $audit->write('schedule.block_created', $business, $request->user(), $block, $data['reason'], [], ['public_id' => $block->public_id, 'kind' => $block->kind], [], 'calendar');

        return back()->with('status', 'Blocked time created.');
    }
}
