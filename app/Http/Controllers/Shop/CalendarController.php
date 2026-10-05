<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Services\ClientWorkspaceQuery;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Domain\SchedulingOperations\Services\CalendarWorkspaceQuery;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function clients(Request $request, Business $business, TenantContext $context, CalendarWorkspaceQuery $workspace): JsonResponse
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::ClientView->value, 'web') &&
            ($membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web') || $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web')), 403);
        $data = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json(['clients' => $workspace->clients($membership, trim($data['search']))]);
    }

    public function __invoke(Request $request, Business $business, CalendarQuery $calendar, TenantContext $context, CalendarWorkspaceQuery $workspace): Response
    {
        $membership = $context->membership();
        abort_unless($membership && ($membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') || $membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web')), 403);

        $locations = Location::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when(! $membership->hasRole('owner', 'web'), fn ($query) => $query->whereIn('id', $membership->locations()->pluck('locations.id')))
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'time_zone', 'business_id', 'status', 'is_active']);
        abort_if($locations->isEmpty(), 403);
        $location = $request->filled('location')
            ? $locations->firstWhere('public_id', $request->string('location')->toString())
            : $locations->first();
        abort_unless($location, 404);

        $staff = StaffProfile::query()
            ->where('business_id', $business->id)
            ->whereHas('locations', fn ($query) => $query->whereKey($location->id))
            ->orderBy('display_name')
            ->get(['id', 'public_id', 'display_name', 'title', 'business_id', 'status']);
        $service = Service::query()->where('business_id', $business->id)->where('is_active', true)->with(['locations', 'addons', 'staffAssignments.staffProfile'])->orderBy('name')->whereHas('locations', fn ($q) => $q->whereKey($location->id)->where('location_service.is_eligible', true))->get();
        $staffIds = $staff->whereIn('public_id', (array) $request->input('staff', []))->pluck('id')->all();
        if (! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web')) {
            abort_unless($membership->staffProfile, 403);
            $staffIds = [$membership->staffProfile->id];
            $staff = $staff->where('id', $membership->staffProfile->id)->values();
        }
        $serviceIds = $service->whereIn('public_id', (array) $request->input('service', []))->pluck('id')->all();
        $statuses = array_values(array_intersect((array) $request->input('status', []), [
            'pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service',
            'completed', 'cancelled_by_client', 'cancelled_by_shop', 'no_show', 'late', 'rescheduled',
        ]));
        $view = in_array($request->input('view'), ['today', 'day', 'week', 'staff', 'agenda'], true) ? $request->input('view') : 'staff';
        $view = $view === 'today' ? 'day' : $view;
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($request->input('date') ?: 'today', $location->time_zone);
        $canContact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $canCheckout = $membership->hasPermissionTo(PermissionName::CheckoutManage->value, 'web');
        $canClient = $membership->hasPermissionTo(PermissionName::ClientView->value, 'web');
        $manageAll = $membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web');
        $manageOwn = $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web');
        $canNotes = $membership->hasPermissionTo(PermissionName::ClientNotesManage->value, 'web');
        $calendarData = $calendar->calendar(new CalendarFilter($business->id, $location->id, $view, $date, $staffIds, $serviceIds, $statuses));
        $notifications = app(\App\Domain\Communications\Services\NotificationWorkspaceQuery::class)->appointmentSummary($business, $membership, collect($calendarData['events'])->where('type', 'appointment')->pluck('id')->all());
        $canCommunications = app(\App\Domain\PlatformAccess\Services\WorkspaceAccessService::class)->decide($business, $membership, 'communications')['allowed'];
        $appointmentStaff = collect($calendarData['events'])->where('type', 'appointment')->flatMap(fn ($event) => collect($event['staff'])->pluck('id'))->unique();
        $displayStaff = $staff->filter(fn ($member) => $member->status === 'active' || $appointmentStaff->contains($member->public_id))->values();
        $missing = $appointmentStaff->diff($staff->pluck('public_id'));
        if ($missing->isNotEmpty()) {
            $displayStaff = $displayStaff->concat(StaffProfile::query()->where('business_id', $business->id)->whereIn('public_id', $missing)
                ->when(! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web'), fn ($q) => $q->whereKey($membership->staffProfile->id))
                ->get(['id', 'public_id', 'display_name', 'title', 'business_id', 'status']));
        }
        $sales = $canCheckout ? DB::table('sales as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.business_id', $business->id)->where('a.business_id', $business->id)->where('s.location_id', $location->id)
            ->whereIn('a.public_id', collect($calendarData['events'])->where('type', 'appointment')->pluck('id'))
            ->get(['a.public_id', 's.status', 's.balance_minor'])->keyBy('public_id') : collect();
        foreach ($calendarData['events'] as &$event) {
            if ($event['type'] !== 'appointment') {
                continue;
            }
            $event['canManage'] = $manageAll || ($manageOwn && collect($event['staff'])->contains('id', $membership->staffProfile?->public_id));
            $event['notifications'] = $notifications[$event['id']] ?? [];
            $event['communicationHistoryUrl'] = $canCommunications ? route('business.communications.page', ['business' => $business->public_id, 'appointment' => $event['id']]) : null;
            if (! $event['canManage']) {
                $event['action'] = null;
            }
            if (! $canClient) {
                $event['clientId'] = null;
            }
            if ($canCheckout && $event['status'] === 'completed') {
                $sale = $sales->get($event['id']);
                $event['checkoutReady'] = ! $sale || ($sale->status === 'open' && $sale->balance_minor > 0);
                $event['paymentLabel'] = $event['checkoutReady'] ? 'Awaiting checkout' : ($sale?->status === 'completed' ? 'Checkout complete' : 'Sale '.$sale?->status);
            }
            if (! $canContact) {
                $event['clientMobile'] = $event['clientEmail'] = null;
            }
            if (! $canNotes) {
                $event['internalNotes'] = null;
            }
        }
        unset($event);

        $clientPrefill = null;
        if ($request->filled('client')) {
            abort_unless($manageAll || $manageOwn, 403);
            $client = Client::query()->where('business_id', $business->id)->where('status', 'active')->where('public_id', $request->string('client')->toString())->firstOrFail();
            $this->authorize('view', $client);
            $lines = [];
            $skipped = 0;
            if ($request->filled('rebook')) {
                $previous = app(ClientWorkspaceQuery::class)->appointments($client, $membership)->where('public_id', $request->string('rebook')->toString())->with('serviceLines.service', 'serviceLines.primaryStaff')->firstOrFail();
                foreach ($previous->serviceLines as $line) {
                    if (! $service->contains('id', $line->service_id)) {
                        $skipped++;

                        continue;
                    }
                    $member = $staff->firstWhere('id', $line->primary_staff_profile_id);
                    $lines[] = ['service' => $line->service->public_id, 'staff' => $member && $member->status === 'active' && ($manageAll || $member->id === $membership->staffProfile?->id) ? $member->public_id : '', 'duration_minutes' => null];
                }
            }
            if (! $request->filled('rebook')) {
                $preferred = $client->preferredServices()->whereIn('services.id', $service->pluck('id'))->get();
                $preferredMember = $staff->firstWhere('id', $client->preferred_staff_profile_id);
                foreach ($preferred as $item) {
                    $lines[] = ['service' => $item->public_id, 'staff' => $preferredMember && $preferredMember->status === 'active' && ($manageAll || $preferredMember->id === $membership->staffProfile?->id) ? $preferredMember->public_id : '', 'duration_minutes' => null];
                }
            }
            $clientPrefill = ['id' => $client->public_id, 'name' => $client->name, 'mobile' => $canContact ? $client->mobile : null, 'email' => $canContact ? $client->email : null,
                'lines' => $lines, 'skippedServices' => $skipped, 'rebooking' => $request->filled('rebook')];
        }

        return Inertia::render('Operations/Calendar', [
            'businessLabel' => $business->name, 'clientPrefill' => $clientPrefill,
            'calendar' => $calendarData,
            'schedule' => $workspace->build($location, $displayStaff->when($staffIds !== [], fn ($items) => $items->whereIn('id', $staffIds)->values()), $date, $view === 'week' ? 7 : 1),
            'filters' => [
                'view' => $view, 'date' => $date->toDateString(), 'location' => $location->public_id,
                'staff' => $staff->whereIn('id', $staffIds)->pluck('public_id')->values()->all(), 'service' => $service->whereIn('id', $serviceIds)->pluck('public_id')->values()->all(), 'status' => $statuses,
            ],
            'options' => [
                'locations' => $locations->map->only(['public_id', 'name', 'time_zone']),
                'staff' => $displayStaff->map->only(['public_id', 'display_name']),
                'bookableStaff' => $staff->where('status', 'active')->when(! $manageAll, fn ($items) => $items->where('id', $membership->staffProfile?->id))->map->only(['public_id', 'display_name'])->values(),
                'services' => $service->map(fn ($s) => [
                    ...$s->only(['public_id', 'name', 'kind', 'price_minor', 'price_type', 'currency_code', 'minimum_notice_minutes', 'duration_minutes', 'processing_minutes', 'cleanup_minutes']),
                    'effective_from' => $s->effective_from?->setTimezone($location->time_zone)->format('Y-m-d\TH:i:s'),
                    'effective_until' => $s->effective_until?->setTimezone($location->time_zone)->format('Y-m-d\TH:i:s'),
                    'addon_ids' => $s->addons->pluck('public_id')->all(),
                    'location_price_minor' => $s->locations->firstWhere('id', $location->id)?->pivot->price_minor,
                    'staff_variants' => $s->staffAssignments->where('is_active', true)->where('is_qualified', true)->filter(fn ($a) => $staff->where('status', 'active')->contains('id', $a->staff_profile_id))->map(fn ($a) => [
                        'staff' => $a->staffProfile->public_id,
                        ...$a->only(['price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes']),
                        'effective_from' => $a->effective_from?->setTimezone($location->time_zone)->format('Y-m-d\TH:i:s'),
                        'effective_until' => $a->effective_until?->setTimezone($location->time_zone)->format('Y-m-d\TH:i:s'),
                    ])->values(),
                ]),
                'statuses' => collect(['pending_confirmation' => 'Pending confirmation', 'confirmed' => 'Confirmed', 'arrived' => 'Arrived', 'checked_in' => 'Checked in', 'in_service' => 'In service', 'completed' => 'Completed', 'late' => 'Late', 'cancelled_by_client' => 'Cancelled by client', 'cancelled_by_shop' => 'Cancelled by shop', 'no_show' => 'No-show', 'rescheduled' => 'Rescheduled'])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'cancellationReasons' => config('reference-data.appointment_cancellation_reasons.business', []),
            ],
            'bookingRules' => [
                'intervalMinutes' => max(1, (int) ($business->appointment_interval_minutes ?: 15)),
                'serverNow' => now()->utc()->toIso8601String(),
            ],
            'permissions' => [
                'contact' => $canContact,
                'notes' => $canNotes,
                'checkout' => $canCheckout, 'client' => $canClient,
                'reassign' => $manageAll,
                'walkIns' => $membership->hasPermissionTo(PermissionName::WalkInsManage->value, 'web'),
                'manage' => $membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web') || $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web'),
                'override' => $membership->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web'),
            ],
        ]);
    }
}
