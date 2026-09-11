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
            'trial_ending' => 'Your free trial is ending soon. Review the available plans before the dated expiry to keep uninterrupted access.',
            'trial_expired' => 'Your free trial has ended. Your existing business information remains available in read-only mode while you choose a plan.',
            'subscription_restricted' => 'The payment recovery period has ended. Existing information remains readable, while protected changes are paused until billing is resolved.',
            'renewal_retry_failed' => 'A renewal retry did not succeed. Update the saved payment method before the dated grace period ends.',
            default => 'Your subscription renewal did not succeed. We will retry automatically and keep billing and export access available.',
        };
        $subject = match ($this->noticeType) {
            'trial_ending' => config('brand.product_name').' trial ending soon',
            'trial_expired' => config('brand.product_name').' trial has ended',
            'subscription_restricted' => config('brand.product_name').' subscription access is limited',
            default => config('brand.product_name').' subscription payment needs attention',
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($message)
            ->when($this->noticeType === 'trial_ending', fn (MailMessage $mail) => $mail->line('Trial ends: '.($this->subscription->trial_ends_at?->utc()->format('Y-m-d H:i').' UTC' ?? 'not scheduled')))
            ->when(in_array($this->noticeType, ['renewal_failed', 'renewal_retry_failed'], true), fn (MailMessage $mail) => $mail->line('Grace period ends: '.($this->subscription->grace_ends_at?->utc()->format('Y-m-d H:i').' UTC' ?? 'not scheduled')))
            ->action('Manage billing', route('business.billing.show', $this->subscription->business));
    }
}
