<?php

namespace App\Http\Controllers\Shop;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintDailyScheduleController extends Controller
{
    public function __invoke(Request $request, Business $business, CalendarQuery $calendar, TenantContext $context): View
    {
        $membership = $context->membership();
        abort_unless($membership && ($membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') || $membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web')), 403);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $request->string('location'))->firstOrFail();
        $staffIds = [];
        if (! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web')) {
            abort_unless($membership->staffProfile, 403);
            $staffIds = [$membership->staffProfile->id];
        }
        $date = CarbonImmutable::parse($request->input('date', 'today'), $location->time_zone);

        return view('operations.daily-schedule', [
            'business' => $business, 'location' => $location, 'date' => $date,
            'calendar' => $calendar->calendar(new CalendarFilter($business->id, $location->id, 'day', $date, $staffIds)),
        ]);
    }
}
