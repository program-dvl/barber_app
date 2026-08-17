<?php

namespace App\Domain\PlatformAccess\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $businessName,
        private readonly string $plainTextToken,
        private readonly CarbonInterface $expiresAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->businessName} on Good Hours")
            ->line("You've been invited to join {$this->businessName}.")
            ->action('Review invitation', route('staff-invitations.show', $this->plainTextToken))
            ->line('This single-use invitation expires '.$this->expiresAt->utc()->toDayDateTimeString().' UTC.');
    }
}
