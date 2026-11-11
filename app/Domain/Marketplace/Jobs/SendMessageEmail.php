<?php

namespace App\Domain\Marketplace\Jobs;

use App\Domain\AccountNotifications\Models\EmailNotificationDelivery;
use App\Domain\AccountNotifications\Services\BusinessNotificationRecipients;
use App\Domain\Marketplace\Models\CustomerBooking;
use App\Domain\Marketplace\Models\Message;
use App\Notifications\BookingMessageNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendMessageEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $messageId)
    {
        $this->onQueue(config('account-notifications.queue', 'emails'));
    }

    public function handle(): void
    {
        if (! config('marketplace.message_email_enabled')) {
            return;
        }
        $message = Message::query()->find($this->messageId);
        if (! $message?->email_notification_id) {
            return;
        }
        $claimed = DB::transaction(function () use ($message) {
            $row = EmailNotificationDelivery::query()->where('notification_id', $message->email_notification_id)->lockForUpdate()->first();
            if (! $row || $row->status !== 'queued') {
                return false;
            }
            $row->update(['status' => 'sending', 'sending_at' => now()]);

            return true;
        });
        if (! $claimed) {
            return;
        }
        $delivery = EmailNotificationDelivery::query()->where('notification_id', $message->email_notification_id)->firstOrFail();
        try {
            $booking = CustomerBooking::query()->with(['customer', 'appointment.business'])->find($message->booking_id);
            $appointment = $booking?->appointment;
            $business = $appointment?->business;
            $valid = $business && $business->isActive() && (int) $booking->business_id === (int) $appointment->business_id && ! $booking->messages_paused && ! $message->read_at;
            $customer = $booking?->customer;
            $valid = $valid && $customer?->email_verified_at && mb_strtolower(trim((string) $appointment->client_email)) === mb_strtolower(trim((string) $customer->email));
            $toBusiness = $message->sender === 'customer';
            $owner = $valid && $toBusiness ? app(BusinessNotificationRecipients::class)->owners($business)->sortBy('id')->first() : null;
            $recipient = $valid ? ($toBusiness ? $owner?->email : $customer->email) : null;
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $delivery->update(['status' => 'skipped', 'last_error_code' => 'recipient_or_conversation_unavailable']);

                return;
            }
            $path = $toBusiness ? route('business.marketplace.thread', [$business, $appointment->public_id], false) : '/marketplace/account/bookings/'.$appointment->public_id.'/messages';
            $notice = new BookingMessageNotification($business->id, $business->name, rtrim(config('brand.website_url'), '/').$path, $toBusiness);
            $notice->id = $message->email_notification_id;
            if ($toBusiness) {
                $owner->notifyNow($notice);
            } else {
                Notification::route('mail', $recipient)->notifyNow($notice);
            }
            // The existing notification listener records actual provider acceptance.
            if ($delivery->fresh()->status === 'sending') {
                $delivery->update(['status' => 'unknown', 'last_error_code' => 'mail_acceptance_unconfirmed']);
            }
        } catch (Throwable $error) {
            report($error);
            $delivery->update(['status' => 'unknown', 'failed_at' => now(), 'last_error_code' => 'mail_outcome_unknown', 'last_error' => 'Review delivery before replaying this alert. The conversation message remains saved.']);
        }
    }
}
