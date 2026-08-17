<?php

namespace App\Domain\PublicBooking\Services;

use App\Domain\ClientRecords\Contracts\ClientIdentityLinker;
use App\Domain\PublicBooking\Models\PublicAppointmentLink;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;

class AppointmentSelfService
{
    public function __construct(
        private readonly AppointmentLifecycleCommand $lifecycle,
        private readonly WaitlistService $waitlist,
        private readonly SecureAppointmentLinkService $links,
        private readonly ClientIdentityLinker $clients,
    ) {}

    public function cancel(PublicAppointmentLink $link, string $idempotencyKey): Appointment
    {
        $appointment = $link->appointment->loadMissing('business');
        $this->assertBeforeCutoff($appointment);
        $cancelled = $this->lifecycle->transition(
            $appointment, 'cancelled_by_client', $idempotencyKey, $appointment->version,
            'self_service', 'public_link', $link->id, 'Client cancelled through a secure self-service link.',
        );
        $link->forceFill(['used_at' => now()])->save();
        $this->links->revokeAppointment($cancelled);
        $this->waitlist->offerForOpening($cancelled);

        return $cancelled;
    }

    public function reschedule(PublicAppointmentLink $link, CarbonImmutable $startsAtUtc, string $idempotencyKey): Appointment
    {
        $appointment = $link->appointment->loadMissing(['business', 'serviceLines.segments']);
        $this->assertBeforeCutoff($appointment);
        $lines = $appointment->serviceLines->map(function ($line): BookingLineRequest {
            $segmentStaff = $line->segments->mapWithKeys(fn ($segment) => [$segment->sequence => $segment->staff_profile_id])->all();

            return new BookingLineRequest($line->service_id, $line->primary_staff_profile_id, $segmentStaff, false);
        })->all();
        $request = new BookingRequest(
            $appointment->business_id, $appointment->location_id, $startsAtUtc, $lines,
            'self_service', 'existing', null, null, 'public_link', $link->id,
            $appointment->client_name, $appointment->client_mobile, $appointment->internal_notes,
            [], null, $appointment->client_email, $appointment->client_date_of_birth?->toDateString(),
            $appointment->referral_source, $appointment->communication_preferences ?? [], $appointment->marketing_opt_in,
            $appointment->special_request, $appointment->public_policy_snapshot ?? [],
        );
        $replacement = $this->lifecycle->replace($appointment, $request, 'reschedule', $idempotencyKey, $appointment->version, 'Client rescheduled through a secure self-service link.');
        $link->forceFill(['used_at' => now()])->save();
        $this->links->revokeAppointment($appointment);
        $this->waitlist->offerForOpening($appointment->fresh());

        return $replacement;
    }

    /** @param array{name?:string,mobile?:string,email?:string} $contact */
    public function updateContact(PublicAppointmentLink $link, array $contact, string $idempotencyKey): Appointment
    {
        $appointment = $this->lifecycle->updateContact($link->appointment, $contact, $idempotencyKey, $link->appointment->version, 'self_service', 'public_link', $link->id);
        $this->clients->synchronizeAppointmentContact($appointment, $contact);
        $link->forceFill(['used_at' => now()])->save();
        $this->links->revokeAppointment($appointment);

        return $appointment;
    }

    private function assertBeforeCutoff(Appointment $appointment): void
    {
        $cutoff = max(0, (int) $appointment->business->cancellation_cutoff_minutes);
        if (CarbonImmutable::now()->utc()->gt($appointment->starts_at_utc->subMinutes($cutoff))) {
            throw new BookingRuleViolation('CANCELLATION_CUTOFF', 'Online changes are closed for this appointment. Contact the business for help.');
        }
    }
}
