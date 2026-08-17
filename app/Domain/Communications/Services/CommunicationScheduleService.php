<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationSetting;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;

class CommunicationScheduleService
{
    public function reminderTime(Appointment $appointment, int $offsetMinutes, CommunicationSetting $settings): CarbonImmutable
    {
        $appointmentLocal = CarbonImmutable::instance($appointment->starts_at_utc)->setTimezone($appointment->time_zone);
        $candidate = $appointmentLocal->subMinutes($offsetMinutes);
        $adjusted = $this->outsideQuietHours($candidate, $settings->quiet_hours_start, $settings->quiet_hours_end);

        if ($adjusted->greaterThanOrEqualTo($appointmentLocal)) {
            $adjusted = $this->beforeQuietHours($candidate, $settings->quiet_hours_start, $settings->quiet_hours_end);
        }

        return $adjusted->utc();
    }

    public function outsideQuietHours(CarbonImmutable $local, string $start, string $end): CarbonImmutable
    {
        $clock = $local->format('H:i:s');
        $start = substr($start, 0, 8);
        $end = substr($end, 0, 8);
        if ($start === $end) {
            return $local;
        }

        $overnight = $start > $end;
        $inside = $overnight ? ($clock >= $start || $clock < $end) : ($clock >= $start && $clock < $end);
        if (! $inside) {
            return $local;
        }

        $date = $local->toDateString();
        if ($overnight && $clock >= $start) {
            $date = $local->addDay()->toDateString();
        }

        return CarbonImmutable::parse($date.' '.$end, $local->getTimezone());
    }

    private function beforeQuietHours(CarbonImmutable $local, string $start, string $end): CarbonImmutable
    {
        $clock = $local->format('H:i:s');
        $start = substr($start, 0, 8);
        $end = substr($end, 0, 8);
        $date = $local->toDateString();

        if ($start > $end && $clock < $end) {
            $date = $local->subDay()->toDateString();
        }

        return CarbonImmutable::parse($date.' '.$start, $local->getTimezone())->subMinute();
    }

    public function suppressFutureAppointmentMessages(Appointment $appointment, string $reason): int
    {
        return CommunicationMessage::query()->where('business_id', $appointment->business_id)
            ->whereHas('intent', fn ($query) => $query->where('source_type', Appointment::class)->where('source_id', $appointment->id)->where('intent_type', 'appointment_reminder'))
            ->whereIn('status', ['queued', 'retried'])->where('queued_at', '<=', now())->update([
                'status' => 'suppressed', 'suppression_reason' => $reason, 'next_attempt_at' => null, 'updated_at' => now(),
            ]);
    }
}
