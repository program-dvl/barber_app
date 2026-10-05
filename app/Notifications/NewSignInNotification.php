<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSignInNotification extends Notification implements BusinessScopedNotification, NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $deviceLabel,
        public readonly string $ipAddress,
        public readonly string $signedInAt,
        public readonly bool $newDevice,
        public readonly ?int $businessId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            (new MailMessage)
                ->subject(($this->newDevice ? 'New sign-in' : 'Sign-in').' to your '.config('brand.product_name').' account')
                ->greeting('Hello '.$notifiable->name.',')
                ->line($this->newDevice
                    ? 'We noticed a successful sign-in from a browser or device we have not seen on your account before.'
                    : 'A successful sign-in to your account was recorded.')
                ->line("Device: {$this->deviceLabel}")
                ->line("IP address: {$this->ipAddress}")
                ->line("Time: {$this->signedInAt}")
                ->action('Review account security', route('profile.show'))
                ->line('If this was you, no action is needed. If you do not recognise it, reset your password and review your active browser sessions immediately.'),
            'security',
            'new_sign_in',
        )->priority(1);
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function notificationStream(): string
    {
        return 'security';
    }
}
