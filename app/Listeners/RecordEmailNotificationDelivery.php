<?php

namespace App\Listeners;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Domain\AccountNotifications\Models\EmailNotificationDelivery;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class RecordEmailNotificationDelivery
{
    public function handle(NotificationSending|NotificationSent|NotificationFailed $event): void
    {
        if ($event->channel !== 'mail' || ! Schema::hasTable('email_notification_deliveries')) {
            return;
        }

        $notification = $event->notification;
        $notificationId = (string) $notification->id;
        if ($notificationId === '') {
            return;
        }

        if ($event instanceof NotificationSending) {
            $delivery = EmailNotificationDelivery::query()->firstOrNew(['notification_id' => $notificationId]);
            $email = $this->recipient($event->notifiable, $notification);
            $delivery->fill([
                'business_id' => $notification instanceof BusinessScopedNotification ? $notification->businessId() : null,
                'user_id' => $event->notifiable instanceof User ? $event->notifiable->getKey() : null,
                'notification_type' => $notification::class,
                'stream' => $notification instanceof NotificationStream ? $notification->notificationStream() : 'account',
                'mailer' => (string) config('mail.default'),
                'recipient_hash' => $email ? hash_hmac('sha256', Str::lower($email), (string) config('app.key')) : null,
                'recipient_masked' => $this->mask($email),
                'status' => 'sending',
                'sending_at' => now(),
                'attempts' => $delivery->exists ? $delivery->attempts + 1 : 1,
                'failed_at' => null,
                'last_error_code' => null,
                'last_error' => null,
            ])->save();

            return;
        }

        $delivery = EmailNotificationDelivery::query()->where('notification_id', $notificationId)->first();
        if (! $delivery) {
            return;
        }

        if ($event instanceof NotificationSent) {
            $delivery->forceFill([
                'status' => 'accepted',
                'provider_message_id' => $this->providerMessageId($event->response),
                'sent_at' => now(),
            ])->save();

            return;
        }

        $exception = $event->data['exception'] ?? null;
        $delivery->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'last_error_code' => $exception instanceof Throwable ? Str::limit($exception::class, 120, '') : 'mail_delivery_failed',
            'last_error' => $exception instanceof Throwable ? Str::limit($exception->getMessage(), 1000, '') : 'The mail channel reported a delivery failure.',
        ])->save();
    }

    private function recipient(object $notifiable, object $notification): ?string
    {
        $route = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor('mail')
            : $notifiable->routeNotificationFor('mail', $notification);

        if (is_string($route)) {
            return $route;
        }

        if (is_array($route)) {
            $key = array_key_first($route);

            return is_string($key) && ! is_int($key) ? $key : (is_string($route[0] ?? null) ? $route[0] : null);
        }

        return null;
    }

    private function mask(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(2, min(8, mb_strlen($local) - mb_strlen($visible)))).'@'.$domain;
    }

    private function providerMessageId(mixed $response): ?string
    {
        if (! is_object($response)) {
            return null;
        }

        try {
            $symfony = method_exists($response, 'getSymfonySentMessage') ? $response->getSymfonySentMessage() : $response;
            $header = $symfony->getOriginalMessage()?->getHeaders()?->get('X-SES-Message-ID');
            if ($header) {
                return Str::limit($header->getBodyAsString(), 255, '');
            }

            return method_exists($symfony, 'getMessageId') ? Str::limit((string) $symfony->getMessageId(), 255, '') : null;
        } catch (Throwable) {
            return null;
        }
    }
}
