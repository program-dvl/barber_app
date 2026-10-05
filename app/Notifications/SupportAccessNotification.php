<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportAccessNotification extends Notification implements BusinessScopedNotification, NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public function __construct(
        public readonly int $businessId,
        public readonly string $businessPublicId,
        public readonly string $businessName,
        public readonly string $change,
        public readonly string $operatorName,
        public readonly string $ticketReference,
        public readonly ?string $expiresAt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $message] = match ($this->change) {
            'granted' => ['Support access approved', "Time-limited support access was approved for {$this->operatorName}. No support session is active until the operator enters your workspace."],
            'entered' => ['Support entered your workspace', "{$this->operatorName} entered your workspace under an approved, scoped support grant."],
            'revoked' => ['Support access revoked', "The support grant for {$this->operatorName} was revoked. Any active session under this grant was ended."],
            default => ['Support access changed', 'A support-access state changed for your workspace.'],
        };

        return $this->brandedMail(
            (new MailMessage)
                ->subject("{$subject} — {$this->businessName}")
                ->greeting('Hello '.$notifiable->name.',')
                ->line($message)
                ->line("Support reference: {$this->ticketReference}")
                ->when($this->expiresAt !== null, fn (MailMessage $mail) => $mail->line("Grant expires: {$this->expiresAt}"))
                ->action('Review activity', route('business.activity.index', $this->businessPublicId))
                ->line('ClipperDesk support access is attributable, scope-limited, expiring and visible in your activity history.'),
            'security',
            'support_access_'.$this->change,
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
