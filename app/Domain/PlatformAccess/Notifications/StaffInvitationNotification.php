<?php

namespace App\Domain\PlatformAccess\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification implements BusinessScopedNotification, NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly int $businessId,
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
        return $this->brandedMail(
            (new MailMessage)
                ->subject("You're invited to {$this->businessName} on ".config('brand.product_name'))
                ->line("You've been invited to join {$this->businessName}.")
                ->action('Review secure invitation', route('staff-invitations.show', $this->plainTextToken))
                ->line('This single-use invitation expires '.$this->expiresAt->utc()->toDayDateTimeString().' UTC. Do not forward this email; the link grants access to the invited address only.')
                ->line('If you did not expect this invitation, you can safely ignore it.'),
            'account',
            'staff_invitation',
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
