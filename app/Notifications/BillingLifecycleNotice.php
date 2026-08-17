<?php

namespace App\Notifications;

use App\Domain\Billing\Models\BusinessSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingLifecycleNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BusinessSubscription $subscription, public readonly string $noticeType) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = match ($this->noticeType) {
            'renewal_retry_failed' => 'A renewal retry did not succeed. Update the saved payment method before the dated grace period ends.',
            default => 'Your subscription renewal did not succeed. We will retry automatically and keep billing and export access available.',
        };

        return (new MailMessage)
            ->subject('Good Hours subscription payment needs attention')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($message)
            ->line('Grace period ends: '.($this->subscription->grace_ends_at?->utc()->format('Y-m-d H:i').' UTC' ?? 'not scheduled'))
            ->action('Manage billing', route('business.billing.show', $this->subscription->business));
    }
}
