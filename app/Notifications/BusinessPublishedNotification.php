<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessPublishedNotification extends Notification implements BusinessScopedNotification, NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public function __construct(
        public readonly int $businessId,
        public readonly string $businessPublicId,
        public readonly string $businessName,
        public readonly string $bookingSlug,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            (new MailMessage)
                ->subject("{$this->businessName} is ready for online bookings")
                ->greeting('Your booking page is live.')
                ->line("{$this->businessName} has passed the required readiness checks and its booking configuration is published.")
                ->action('View booking page', route('booking.business', ['slug' => $this->bookingSlug]))
                ->line('Share the page when you are ready. Future changes to availability, services and policies remain controlled from your workspace.'),
            'account',
            'business_published',
        );
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function notificationStream(): string
    {
        return 'account';
    }
}
