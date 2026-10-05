<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\SchedulingOperations\Services\AppointmentLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DailyWorkspaceService
{
    /** A permission-filtered projection; the calendar and lifecycle remain the operational truth. */
    public function build(Business $business, Membership $membership, Location $location, CarbonImmutable $date, array $calendar, array $metrics, array $permissions): array
    {
        $own = ! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web');
        $ownPublicId = $membership->staffProfile?->public_id;
        $contact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $notes = $membership->hasPermissionTo(PermissionName::ClientNotesManage->value, 'web');
        $manageAll = $membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web');
        $manageOwn = $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web');
        $now = CarbonImmutable::now($location->time_zone);
        $isToday = $date->toDateString() === $now->toDateString();
        $base = "/businesses/{$business->public_id}";
        $calendarHref = "{$base}/app/calendar?location={$location->public_id}&date={$date->toDateString()}";
        $settledPublicIds = $permissions['checkout'] ? DB::table('sales as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.business_id', $business->id)->where('s.location_id', $location->id)->where('s.status', 'completed')
            ->whereIn('a.public_id', collect($calendar['events'])->where('type', 'appointment')->pluck('id'))->pluck('a.public_id')->all() : [];
        $appointments = collect($calendar['events'])->where('type', 'appointment')->map(function (array $event) use ($contact, $notes, $manageAll, $manageOwn, $ownPublicId, $calendarHref, $permissions, $base, $settledPublicIds): array {
            $canManage = $manageAll || ($manageOwn && collect($event['staff'])->contains('id', $ownPublicId));

            return [
                ...array_intersect_key($event, array_flip(['id', 'title', 'status', 'statusLabel', 'tone', 'startsAt', 'endsAt', 'services', 'staff', 'unassigned', 'forms', 'version'])),
                'clientMobile' => $contact ? $event['clientMobile'] : null,
                'clientEmail' => $contact ? $event['clientEmail'] : null,
                'internalNotes' => $notes ? $event['internalNotes'] : null,
                'action' => $canManage ? AppointmentLifecycleService::primaryActionFor($event['status']) : null,
                'href' => $calendarHref.'&appointment='.$event['id'].(in_array($event['status'], ['cancelled_by_client', 'cancelled_by_shop', 'rescheduled'], true) ? '&status[]='.$event['status'] : ''),
                'checkoutHref' => $permissions['checkout'] && $event['status'] === 'completed' && ! in_array($event['id'], $settledPublicIds, true) ? "{$base}/app/checkout-sales?appointment={$event['id']}" : null,
            ];
        })->values()->all();

        $checkoutQuery = DB::table('appointments as a')->leftJoin('sales as s', fn ($join) => $join->on('s.appointment_id', '=', 'a.id')->on('s.business_id', '=', 'a.business_id'))
            ->where('a.business_id', $business->id)->where('a.location_id', $location->id)->where('a.status', 'completed')
            ->where(fn ($query) => $query->whereNull('s.id')->orWhere(fn ($open) => $open->where('s.status', 'open')->where('s.balance_minor', '>', 0)));
        $canCheckout = $permissions['checkout'] && $permissions['calendar'] && ! $own;
        $checkoutCount = $canCheckout ? (clone $checkoutQuery)->count() : 0;
        $checkouts = $canCheckout ? $checkoutQuery->orderBy('a.starts_at_utc')->limit(8)->get(['a.public_id', 'a.client_name', 'a.starts_at_utc', 'a.currency_code', 'a.price_minor', 's.balance_minor'])
            ->map(fn ($row) => ['id' => $row->public_id, 'name' => $row->client_name ?: 'Client', 'date' => CarbonImmutable::parse($row->starts_at_utc, 'UTC')->setTimezone($location->time_zone)->toDateString(), 'amount' => (int) ($row->balance_minor ?? $row->price_minor), 'currency' => $row->currency_code, 'href' => "{$base}/app/checkout-sales?appointment={$row->public_id}"])->all() : [];

        $hours = array_map(fn ($window) => [
            'startsAt' => CarbonImmutable::parse($date->toDateString().' '.$window['opens_at'], $location->time_zone)->toIso8601String(),
            'endsAt' => CarbonImmutable::parse($date->toDateString().' '.$window['closes_at'], $location->time_zone)->toIso8601String(),
        ], $metrics['hours']);
        $phase = 'closed';
        if ($hours !== []) {
            $phase = 'before_open';
            foreach ($hours as $window) {
                if ($now->greaterThanOrEqualTo(CarbonImmutable::parse($window['startsAt'])) && $now->lessThan(CarbonImmutable::parse($window['endsAt']))) {
                    $phase = $now->diffInMinutes(CarbonImmutable::parse($window['endsAt'])) <= 60 ? 'closing_soon' : 'open';
                    break;
                }
                if ($now->greaterThanOrEqualTo(CarbonImmutable::parse($window['endsAt']))) {
                    $phase = 'after_close';
                }
            }
            if ($phase === 'after_close' && $now->lessThan(CarbonImmutable::parse(end($hours)['startsAt']))) {
                $phase = 'between_hours';
            }
        }

        return [
            'scope' => $permissions['calendar'] ? ($own ? 'personal' : 'location') : ($permissions['revenue'] ? 'finance' : 'limited'),
            'isToday' => $isToday,
            'today' => $now->toDateString(),
            'phase' => $isToday ? $phase : 'other_date',
            'hours' => $hours,
            'appointments' => $appointments,
            'truncated' => $calendar['truncated'] ?? false,
            'team' => $metrics['team'],
            'queue' => $permissions['walkIns'] && $isToday ? DB::table('walk_in_entries')->where('business_id', $business->id)->where('location_id', $location->id)->whereIn('status', ['waiting', 'notified', 'assigned'])->orderBy('queue_position')->limit(8)->get(['public_id', 'client_name', 'status', 'queue_position', 'estimated_wait_minutes'])->map(fn ($entry) => ['id' => $entry->public_id, 'title' => $entry->client_name, 'status' => $entry->status, 'queuePosition' => $entry->queue_position, 'estimatedWaitMinutes' => $entry->estimated_wait_minutes])->all() : [],
            'blocks' => collect($calendar['events'])->where('type', 'block')->map(fn ($event) => array_intersect_key($event, array_flip(['id', 'title', 'statusLabel', 'startsAt', 'endsAt'])))->values()->all(),
            'checkouts' => $checkouts,
            'checkoutCount' => $checkoutCount,
        ];
    }
}
