<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Data\CommunicationIntentData;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Models\WaitlistMatch;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationalCommunicationService
{
    public function __construct(
        private readonly NotificationIntentService $intents,
        private readonly CommunicationTemplateService $templates,
        private readonly CommunicationScheduleService $schedule,
    ) {}

    public function process(OperationalNotificationEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $event = OperationalNotificationEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status !== 'pending') {
                return;
            }
            $handled = match ($event->subject_type) {
                Appointment::class => $this->appointment($event),
                WaitlistMatch::class => $this->waitlist($event),
                WalkInEntry::class => $this->walkIn($event),
                default => false,
            };
            $event->update(['status' => $handled ? 'processed' : 'ignored']);
        }, 3);
    }

    public function processPending(?int $businessId = null, int $limit = 100): int
    {
        $events = OperationalNotificationEvent::query()->where('status', 'pending')->when($businessId, fn ($query) => $query->where('business_id', $businessId))->orderBy('id')->limit($limit)->get();
        $events->each(fn (OperationalNotificationEvent $event) => $this->process($event));

        return $events->count();
    }

    public function scheduleReminders(Appointment $appointment): int
    {
        $settings = $this->templates->settings($appointment->business_id);
        $created = 0;
        foreach ($settings->reminder_offsets_minutes as $offset) {
            $when = $this->schedule->reminderTime($appointment, (int) $offset, $settings);
            if ($when->lessThanOrEqualTo(now())) {
                continue;
            }
            $this->appointmentIntent($appointment, 'appointment_reminder', 'appointment.reminder', 'appointment:'.$appointment->id.':reminder:'.$offset, $when, []);
            $created++;
        }

        return $created;
    }

    private function appointment(OperationalNotificationEvent $event): bool
    {
        $appointment = Appointment::query()->where('business_id', $event->business_id)->with(['client', 'location', 'serviceLines.primaryStaff'])->find($event->subject_id);
        if (! $appointment) {
            return false;
        }
        $intent = match ($event->event_type) {
            'appointment.created', 'appointment.confirmed' => 'booking_confirmation',
            'appointment.pending' => 'booking_pending',
            'appointment.approved' => 'booking_approved',
            'appointment.rejected' => 'booking_rejected',
            'appointment.cancelled' => 'appointment_cancelled',
            'appointment.reschedule', 'appointment.move', 'appointment.resize', 'appointment.reassign', 'appointment.services_changed', 'appointment.contact_changed' => 'appointment_changed',
            'deposit.requested' => 'deposit_request', 'deposit.received' => 'deposit_received',
            'payment.receipt' => 'payment_receipt', 'appointment.feedback_requested' => 'feedback_request',
            'appointment.rebooking_due' => 'rebooking_reminder',
            'appointment.status_changed' => match (data_get($event->payload, 'status')) {
                'confirmed' => 'booking_approved',
                'cancelled_by_client', 'cancelled_by_shop' => 'appointment_cancelled',
                default => null,
            },
            default => null,
        };
        if (! $intent) {
            return false;
        }
        if (in_array($intent, ['appointment_changed', 'appointment_cancelled'], true)) {
            $this->schedule->suppressFutureAppointmentMessages($appointment, $intent);
        }
        $this->appointmentIntent($appointment, $intent, $event->event_type, $event->idempotency_key, CarbonImmutable::instance($event->occurred_at), $event->payload ?? []);
        if (in_array($intent, ['booking_confirmation', 'booking_approved', 'appointment_changed'], true) && ! in_array($appointment->status, ['cancelled_by_client', 'cancelled_by_shop', 'rescheduled'], true)) {
            $this->scheduleReminders($appointment);
        }

        return true;
    }

    private function appointmentIntent(Appointment $appointment, string $intent, string $eventType, string $eventKey, CarbonImmutable $when, array $payload): void
    {
        $appointment->loadMissing(['business', 'client', 'location', 'serviceLines.primaryStaff']);
        $preferences = is_array($appointment->communication_preferences) ? $appointment->communication_preferences : [];
        $clientPreferences = is_array($appointment->client?->communication_preferences) ? $appointment->client->communication_preferences : [];
        $emailOptedOut = ($preferences['email'] ?? null) === false || ($clientPreferences['email'] ?? null) === false;
        $recipients = [];
        if (! $emailOptedOut && $appointment->client_email) {
            $recipients['email'] = $appointment->client_email;
        }
        $local = $appointment->starts_at_utc->setTimezone($appointment->time_zone);
        $defaults = TemplateVariableCatalog::defaults($intent);
        $this->intents->create(new CommunicationIntentData(
            $appointment->business_id, $eventKey, $eventType, $intent, $defaults['category'],
            $defaults['category'] === 'marketing' ? 'explicit_marketing_consent' : 'contract_performance',
            $appointment->business->locale ?: 'en-IN', $appointment->time_zone, $when->utc(), $recipients,
            [
                'client_name' => $appointment->client_name, 'service_name' => $appointment->serviceLines->pluck('name')->join(', '),
                'staff_name' => $appointment->serviceLines->pluck('primaryStaff.display_name')->filter()->unique()->join(', '),
                'location_name' => $appointment->location->name, 'appointment_date' => $local->isoFormat('D MMMM YYYY'),
                'appointment_time' => $local->format('H:i'), 'time_zone' => $appointment->time_zone,
                'booking_reference' => $appointment->booking_reference, 'amount' => number_format(((int) data_get($payload, 'amount_minor', $appointment->price_minor)) / 100, 2, '.', ''),
                'currency' => data_get($payload, 'currency', $appointment->currency_code), 'business_name' => $appointment->business->name,
            ], $appointment->client_id, Appointment::class, $appointment->id, (string) Str::uuid(), $defaults['action_purpose'],
        ));
    }

    private function waitlist(OperationalNotificationEvent $event): bool
    {
        $match = WaitlistMatch::query()->where('business_id', $event->business_id)->with(['request.location', 'request.service'])->find($event->subject_id);
        if (! $match) {
            return false;
        }
        $method = $match->request->notification_method;
        $destination = $method === 'email' ? $match->request->client_email : $match->request->client_mobile;
        if (! in_array($method, ['email', 'whatsapp'], true) || ! $destination) {
            return false;
        }
        $local = $match->slot_starts_at_utc->setTimezone($match->request->location->time_zone);
        $business = Business::query()->findOrFail($event->business_id);
        $this->intents->create(new CommunicationIntentData(
            $event->business_id, $event->idempotency_key, $event->event_type, 'waitlist_opening', 'transactional',
            'explicit_channel_request', $business->locale ?: 'en-IN', $match->request->location->time_zone,
            CarbonImmutable::instance($event->occurred_at), [$method => $destination], [
                'client_name' => $match->request->client_name, 'service_name' => $match->request->service->name,
                'location_name' => $match->request->location->name, 'appointment_date' => $local->isoFormat('D MMMM YYYY'),
                'appointment_time' => $local->format('H:i'), 'time_zone' => $match->request->location->time_zone,
            ], null, WaitlistMatch::class, $match->id, (string) Str::uuid(), 'waitlist_claim',
        ));

        return true;
    }

    private function walkIn(OperationalNotificationEvent $event): bool
    {
        $entry = WalkInEntry::query()->where('business_id', $event->business_id)->with(['client'])->find($event->subject_id);
        if (! $entry) {
            return false;
        }
        $business = Business::query()->with('locations')->findOrFail($event->business_id);
        $clientPreferences = is_array($entry->client?->communication_preferences) ? $entry->client->communication_preferences : [];
        $recipients = ! (($clientPreferences['email'] ?? null) === false) && $entry->client_email
            ? ['email' => $entry->client_email]
            : [];
        if ($recipients === []) {
            return false;
        }
        $this->intents->create(new CommunicationIntentData(
            $event->business_id, $event->idempotency_key, $event->event_type, 'queue_update', 'transactional',
            'contract_performance', $business->locale ?: 'en-IN', $business->time_zone ?: 'Asia/Kolkata',
            CarbonImmutable::instance($event->occurred_at), $recipients, [
                'client_name' => $entry->client_name, 'queue_estimate' => $entry->estimated_wait_minutes.' minutes',
                'location_name' => $business->locations->firstWhere('id', $entry->location_id)?->name ?? $business->name,
            ], $entry->client_id, WalkInEntry::class, $entry->id, (string) Str::uuid(), null,
        ));

        return true;
    }
}
