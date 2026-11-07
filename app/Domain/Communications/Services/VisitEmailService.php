<?php

namespace App\Domain\Communications\Services;

use App\Domain\ClientReviews\Models\ClientReview;
use App\Domain\Communications\Data\CommunicationIntentData;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleReceipt;
use App\Domain\SchedulingOperations\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One receipt email and one review invitation per visit, shared by automatic and staff actions. */
class VisitEmailService
{
    public function request(Appointment $appointment, string $type, bool $automatic = false): CommunicationIntent
    {
        return DB::transaction(function () use ($appointment, $type, $automatic) {
            $appointment = Appointment::query()->where('business_id', $appointment->business_id)->lockForUpdate()->findOrFail($appointment->id);
            $appointment->load(['client', 'business']);
            $reason = $this->blocked($appointment, $type, $automatic);
            if ($reason) {
                throw ValidationException::withMessages(['email' => $reason]);
            }
            $existing = CommunicationIntent::query()->where('business_id', $appointment->business_id)->where('intent_type', $type)->where('source_type', Appointment::class)->where('source_id', $appointment->id)->where('status', '!=', 'suppressed')->oldest('id')->first();
            if ($existing) {
                return $existing->load('messages');
            }
            $review = $type === 'visit_review';
            $receipt = $review ? null : SaleReceipt::query()->where('business_id', $appointment->business_id)->whereIn('sale_id', Sale::query()->select('id')->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id))->firstOrFail();
            $currency = $receipt ? data_get($receipt->snapshot, 'sale.currency_code', $appointment->currency_code) : $appointment->currency_code;
            $formatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);
            $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency);
            $digits = $formatter->getAttribute(\NumberFormatter::MAX_FRACTION_DIGITS);
            $amount = number_format(data_get($receipt?->snapshot, 'sale.total_minor', 0) / (10 ** $digits), $digits, '.', ',');

            return app(NotificationIntentService::class)->create(new CommunicationIntentData(
                $appointment->business_id, 'visit:'.$appointment->id.':'.$type, $automatic ? 'visit.email.automatic' : 'visit.email.manual',
                $type, $review ? 'marketing' : 'transactional', $review ? 'completed_visit_review' : 'contract_performance',
                $appointment->business->locale ?: 'en-US', $appointment->time_zone, now()->toImmutable(),
                ['email' => $appointment->client_email], ['business_name' => $appointment->business->name, 'client_name' => $appointment->client_name,
                    'booking_reference' => $appointment->booking_reference, 'currency' => $currency, 'amount' => $amount],
                $appointment->client_id, Appointment::class, $appointment->id,
            ));
        }, 3);
    }

    public function deliveryFeedback(CommunicationIntent $intent): string
    {
        return match ($intent->status) {
            'sent', 'delivered' => 'This email has already been sent. See Client notifications for delivery details.',
            'failed' => 'This email could not be sent. Open Client notifications for the delivery details and contact ClipperDesk support with reference '.$intent->correlation_id.'.',
            'suppressed' => 'This email was stopped by notification preferences or a delivery restriction. Review its details in Client notifications.',
            default => 'Email requested. Track its delivery in Client notifications.',
        };
    }

    public function blocked(Appointment $appointment, string $type, bool $automatic): ?string
    {
        if (! in_array($type, ['visit_review', 'payment_receipt'], true)) {
            return 'This email is unavailable.';
        }
        $settings = app(CommunicationTemplateService::class)->settings($appointment->business_id);
        if ($automatic && ! $settings->{$type === 'visit_review' ? 'automatic_review_email' : 'automatic_receipt_email'}) {
            return 'Automatic emails are turned off for this message.';
        }
        if ($appointment->status !== 'completed' || ($type === 'visit_review' && (! $appointment->completed_at || $appointment->completed_at->isFuture()))) {
            return 'Complete this visit before sending an email.';
        }
        if ($type === 'visit_review') {
            if (ClientReview::query()->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id)->exists()) {
                return 'Feedback has already been received for this visit.';
            }
            if ($automatic && (! $settings->review_email_enabled_at || ! $appointment->completed_at || $appointment->completed_at->lt($settings->review_email_enabled_at))) {
                return 'This visit was completed before automatic review emails were enabled.';
            }
        } elseif (! SaleReceipt::query()->where('business_id', $appointment->business_id)->whereIn('sale_id', Sale::query()->select('id')->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id))->exists()) {
            return 'The receipt is not available yet. Finish checkout first.';
        }
        if ($automatic && $type === 'payment_receipt' && $settings->receipt_email_enabled_at && ! SaleReceipt::query()->where('business_id', $appointment->business_id)->where('issued_at', '>=', $settings->receipt_email_enabled_at)->whereIn('sale_id', Sale::query()->select('id')->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id))->exists()) {
            return 'This receipt was issued before automatic receipt emails were enabled again.';
        }
        if (! $appointment->client_email) {
            return 'Add an email address to this appointment before sending.';
        }
        if (($appointment->communication_preferences['email'] ?? null) === false || ($appointment->client?->communication_preferences['email'] ?? null) === false) {
            return 'This client has turned off email notifications.';
        }
        $decision = app(CommunicationConsentService::class)->decision($settings, $appointment->client, 'email', $appointment->client_email,
            $type === 'visit_review' ? 'marketing' : 'transactional', $type === 'visit_review' ? 'completed_visit_review' : 'contract_performance');

        return $decision['allowed'] ? null : 'This email cannot be sent because email is paused, permission is missing, or this address is blocked. Review Client notifications and the client’s communication preferences.';
    }
}
