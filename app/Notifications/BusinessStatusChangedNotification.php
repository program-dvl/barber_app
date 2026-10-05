<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessStatusChangedNotification extends Notification implements BusinessScopedNotification, NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public function __construct(
        public readonly int $businessId,
        public readonly string $businessPublicId,
        public readonly string $businessName,
        public readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $message] = match ($this->status) {
            'suspended' => ['Business access suspended', 'Platform Operations suspended this business workspace. Sign-in identity remains intact, but tenant access is restricted until the status is resolved.'],
            'closed' => ['Business workspace closed', 'Platform Operations closed this business workspace. Historical and export treatment follows the applicable retention and billing policy.'],
            'active' => ['Business access restored', 'This business workspace is active again. Authorised team members can resume normal access.'],
            default => ['Business status changed', "The workspace status changed to {$this->status}."],
        };

        return $this->brandedMail(
            (new MailMessage)
                ->subject("{$subject} — {$this->businessName}")
                ->greeting('Hello '.$notifiable->name.',')
                ->line($message)
                ->action('Open ClipperDesk', config('brand.website_url'))
                ->line('If you did not expect this change, contact ClipperDesk support and include your business name.'),
            'security',
            'business_status_'.$this->status,
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
