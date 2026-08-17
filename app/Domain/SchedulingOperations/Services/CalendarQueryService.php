<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\ScheduleBlock;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use Carbon\CarbonImmutable;

class CalendarQueryService implements CalendarQuery
{
    private const STATUS_PRESENTATION = [
        'pending_confirmation' => ['label' => 'Pending confirmation', 'cue' => 'Needs review', 'tone' => 'warning'],
        'confirmed' => ['label' => 'Confirmed', 'cue' => 'Booked', 'tone' => 'info'],
        'arrived' => ['label' => 'Arrived', 'cue' => 'On premises', 'tone' => 'success'],
        'checked_in' => ['label' => 'Checked in', 'cue' => 'Ready', 'tone' => 'success'],
        'in_service' => ['label' => 'In service', 'cue' => 'Underway', 'tone' => 'strong'],
        'completed' => ['label' => 'Completed', 'cue' => 'Done', 'tone' => 'neutral'],
        'cancelled_by_client' => ['label' => 'Cancelled by client', 'cue' => 'Cancelled', 'tone' => 'danger'],
        'cancelled_by_shop' => ['label' => 'Cancelled by shop', 'cue' => 'Cancelled', 'tone' => 'danger'],
        'no_show' => ['label' => 'No-show', 'cue' => 'Did not attend', 'tone' => 'danger'],
        'late' => ['label' => 'Late', 'cue' => 'Delayed', 'tone' => 'warning'],
        'rescheduled' => ['label' => 'Rescheduled', 'cue' => 'Moved', 'tone' => 'neutral'],
    ];

    public function calendar(CalendarFilter $filter): array
    {
        if (! in_array($filter->view, ['today', 'day', 'week', 'staff'], true)) {
            throw new \InvalidArgumentException('Unsupported calendar view.');
        }
        $location = Location::query()->where('business_id', $filter->businessId)->findOrFail($filter->locationId);
        $localStart = $filter->localDate->setTimezone($location->time_zone)->startOfDay();
        $localEnd = $filter->view === 'week' ? $localStart->addDays(7) : $localStart->addDay();
        $startsAt = $localStart->utc();
        $endsAt = $localEnd->utc();

        $appointments = Appointment::query()
            ->where('business_id', $filter->businessId)
            ->where('location_id', $location->id)
            ->where('starts_at_utc', '<', $endsAt)
            ->where('ends_at_utc', '>', $startsAt)
            ->when($filter->statuses !== [], fn ($query) => $query->whereIn('status', $filter->statuses))
            ->when($filter->staffIds !== [], fn ($query) => $query->whereHas('segments', fn ($segments) => $segments->whereIn('staff_profile_id', $filter->staffIds)))
            ->when($filter->serviceIds !== [], fn ($query) => $query->whereHas('serviceLines', fn ($lines) => $lines->whereIn('service_id', $filter->serviceIds)))
            ->withCount([
                'formRequests as forms_requested_count',
                'formRequests as forms_completed_count' => fn ($query) => $query->where('status', 'completed'),
            ])
            ->with(['serviceLines:id,appointment_id,name,service_id,primary_staff_profile_id,bookable_minutes', 'serviceLines.service:id,public_id', 'serviceLines.primaryStaff:id,public_id,display_name', 'segments:id,appointment_id,staff_profile_id,starts_at_utc,ends_at_utc,kind,occupies_staff', 'segments.staff:id,public_id,display_name'])
            ->orderBy('starts_at_utc')
            ->limit(1000)
            ->get();

        $blocks = ScheduleBlock::query()
            ->where('business_id', $filter->businessId)
            ->where('location_id', $location->id)
            ->where('starts_at_utc', '<', $endsAt)
            ->where('ends_at_utc', '>', $startsAt)
            ->when($filter->staffIds !== [], fn ($query) => $query->whereIn('staff_profile_id', $filter->staffIds))
            ->orderBy('starts_at_utc')
            ->get();

        $walkIns = WalkInEntry::query()
            ->where('business_id', $filter->businessId)
            ->where('location_id', $location->id)
            ->whereIn('status', ['waiting', 'notified', 'assigned'])
            ->when($filter->staffIds !== [], fn ($query) => $query->where(function ($staff) use ($filter): void {
                $staff->whereIn('assigned_staff_profile_id', $filter->staffIds)
                    ->orWhereIn('preferred_staff_profile_id', $filter->staffIds);
            }))
            ->when($filter->serviceIds !== [], fn ($query) => $query->whereIn('service_id', $filter->serviceIds))
            ->orderBy('queue_position')
            ->get();

        return [
            'view' => $filter->view,
            'timeZone' => $location->time_zone,
            'range' => ['startsAt' => $localStart->toIso8601String(), 'endsAt' => $localEnd->toIso8601String()],
            'currentTime' => CarbonImmutable::now($location->time_zone)->toIso8601String(),
            'events' => [
                ...$appointments->map(fn (Appointment $appointment) => $this->appointmentEvent($appointment, $location->time_zone))->all(),
                ...$blocks->map(fn (ScheduleBlock $block) => $this->blockEvent($block, $location->time_zone))->all(),
                ...$walkIns->map(fn (WalkInEntry $entry) => $this->walkInEvent($entry, $location->time_zone))->all(),
            ],
            'counts' => [
                'appointments' => $appointments->count(),
                'blocks' => $blocks->count(),
                'walkInsWaiting' => $walkIns->count(),
                'unassigned' => $appointments->filter(fn (Appointment $appointment) => $appointment->segments->every(fn ($segment) => $segment->staff_profile_id === null))->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function appointmentEvent(Appointment $appointment, string $timeZone): array
    {
        $presentation = self::STATUS_PRESENTATION[$appointment->status] ?? ['label' => ucfirst($appointment->status), 'cue' => 'Status', 'tone' => 'neutral'];
        $staff = $appointment->segments->pluck('staff')->filter()->unique('id')->values();

        return [
            'id' => $appointment->public_id,
            'type' => 'appointment',
            'status' => $appointment->status,
            'statusLabel' => $presentation['label'],
            'statusCue' => $presentation['cue'],
            'tone' => $presentation['tone'],
            'title' => $appointment->client_name ?: 'Unassigned client',
            'clientName' => $appointment->client_name,
            'clientMobile' => $appointment->client_mobile,
            'internalNotes' => $appointment->internal_notes,
            'services' => $appointment->serviceLines->map(fn ($line) => [
                'id' => $line->service?->public_id,
                'name' => $line->name,
                'staffId' => $line->primaryStaff?->public_id,
                'durationMinutes' => $line->bookable_minutes,
            ])->all(),
            'staff' => $staff->map(fn ($member) => ['id' => $member->public_id, 'name' => $member->display_name])->all(),
            'unassigned' => $staff->isEmpty(),
            'startsAt' => $appointment->starts_at_utc->setTimezone($timeZone)->toIso8601String(),
            'endsAt' => $appointment->ends_at_utc->setTimezone($timeZone)->toIso8601String(),
            'source' => $appointment->source,
            'forms' => [
                'requested' => $appointment->forms_requested_count,
                'completed' => $appointment->forms_completed_count,
                'pending' => $appointment->forms_requested_count - $appointment->forms_completed_count,
                'status' => $appointment->forms_requested_count === 0 ? 'none' : ($appointment->forms_requested_count === $appointment->forms_completed_count ? 'completed' : 'pending'),
            ],
            'version' => $appointment->version,
        ];
    }

    /** @return array<string, mixed> */
    private function blockEvent(ScheduleBlock $block, string $timeZone): array
    {
        return [
            'id' => $block->public_id,
            'type' => 'block',
            'status' => $block->kind,
            'statusLabel' => $block->kind === 'staff_break' ? 'Staff break' : 'Blocked time',
            'statusCue' => 'Unavailable',
            'tone' => 'neutral',
            'title' => $block->label,
            'staff' => [['id' => $block->staff_profile_id]],
            'startsAt' => $block->starts_at_utc->setTimezone($timeZone)->toIso8601String(),
            'endsAt' => $block->ends_at_utc->setTimezone($timeZone)->toIso8601String(),
            'version' => $block->version,
        ];
    }

    /** @return array<string, mixed> */
    private function walkInEvent(WalkInEntry $entry, string $timeZone): array
    {
        return [
            'id' => $entry->public_id,
            'type' => 'walk_in',
            'status' => $entry->status,
            'statusLabel' => 'Walk-in '.str_replace('_', ' ', $entry->status),
            'statusCue' => 'Queue #'.$entry->queue_position,
            'tone' => 'warning',
            'title' => $entry->client_name,
            'startsAt' => ($entry->estimated_service_at ?? $entry->arrived_at)->setTimezone($timeZone)->toIso8601String(),
            'endsAt' => null,
            'queuePosition' => $entry->queue_position,
            'estimatedWaitMinutes' => $entry->estimated_wait_minutes,
            'version' => $entry->version,
        ];
    }
}
