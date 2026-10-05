<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements BusinessScopedNotification, NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $businessId,
        public readonly string $businessPublicId,
        public readonly string $businessName,
        public readonly string $trialEndsAt,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            (new MailMessage)
                ->subject("Your ClipperDesk workspace is ready — {$this->businessName}")
                ->greeting('Welcome to ClipperDesk, '.$notifiable->name.'.')
                ->line("Your verified workspace for **{$this->businessName}** is ready. Your trial runs through **{$this->trialEndsAt}**.")
                ->line('Begin with the essentials: your business details, opening hours, team and core services. Then preview the client experience before publishing your booking page.')
                ->action('Continue workspace setup', route('business.configuration.show', $this->businessPublicId))
                ->line('Everything stays editable, so you can start with a strong foundation and refine the details as your business grows.'),
            'account',
            'workspace_welcome',
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function notificationStream(): string
    {
        return 'account';
    }
}
