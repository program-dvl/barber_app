<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\AccountNotifications\Models\EmailNotificationDelivery;
use App\Domain\Marketplace\Jobs\SendMessageEmail;
use App\Domain\Marketplace\Models\CustomerBooking;
use App\Domain\Marketplace\Models\Message;
use App\Notifications\BookingMessageNotification;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingMessages
{
    public function send(CustomerBooking $booking, string $sender, string $body, string $key, ?int $staffId = null): Message
    {
        return DB::transaction(function () use ($booking, $sender, $body, $key, $staffId) {
            $booking = CustomerBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $existing = Message::query()->where('booking_id', $booking->id)->where('sender', $sender)->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->body !== $body || $existing->staff_user_id !== $staffId) {
                    throw ValidationException::withMessages(['body' => 'This message was already sent with different text. Refresh the conversation before sending another message.']);
                }

                return $existing;
            }
            if ($booking->messages_paused) {
                throw ValidationException::withMessages(['body' => 'Messages are paused for this booking. Contact Clipperdesk support if you need help.']);
            }
            abort_unless($booking->appointment->business->isActive(), 409, 'This business is not accepting messages. Your booking remains saved.');
            $notificationId = config('marketplace.message_email_enabled') ? (string) Str::uuid() : null;
            $message = Message::query()->create(['booking_id' => $booking->id, 'sender' => $sender, 'staff_user_id' => $staffId, 'body' => $body, 'idempotency_key' => $key, 'email_notification_id' => $notificationId]);
            if ($notificationId) {
                EmailNotificationDelivery::query()->create(['notification_id' => $notificationId, 'business_id' => $booking->business_id, 'notification_type' => BookingMessageNotification::class, 'stream' => $sender === 'customer' ? 'account' : 'marketplace', 'mailer' => config('mail.default'), 'status' => 'queued', 'attempts' => 0]);
                DB::afterCommit(function () use ($message) {
                    try {
                        SendMessageEmail::dispatch($message->id);
                    } catch (\Throwable $error) {
                        report($error);
                    }
                });
            }
            app(AuditWriter::class)->write('marketplace.message.sent', $booking->appointment->business, target: $message, metadata: ['customer_public_id' => $booking->customer->public_id, 'sender' => $sender], source: $sender === 'customer' ? 'marketplace_customer' : 'application');

            return $message;
        });
    }

    public function thread(CustomerBooking $booking, string $viewer, ?string $before = null): array
    {
        // Only mark the page actually displayed as read; older messages remain unread.
        $records = Message::query()->where('booking_id', $booking->id)->when($before, fn ($q) => $q->where('public_id', '<', $before))->orderByDesc('public_id')->limit(51)->get();
        $messages = $records->take(50)->reverse()->values();
        Message::query()->whereIn('id', $messages->pluck('id'))->where('sender', '!=', $viewer)->whereNull('read_at')->update(['read_at' => now()]);

        return ['next' => $records->count() > 50 ? $messages->first()->public_id : null, 'items' => $messages->map(fn (Message $message) => [
            'id' => $message->public_id, 'sender' => $message->sender, 'body' => $message->body,
            'sent_at' => $message->created_at->toIso8601String(), 'read' => (bool) $message->read_at || $message->sender !== $viewer,
        ])->all()];
    }
}
