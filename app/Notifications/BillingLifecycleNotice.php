<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingLifecycleNotice extends Notification implements BusinessScopedNotification, NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly BusinessSubscription $subscription, public readonly string $noticeType) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = config('brand.product_name');
        [$subject, $message] = match ($this->noticeType) {
            'trial_ending' => ["Your {$product} trial ends soon", 'Your free trial is ending soon. Choose a plan before the dated expiry to keep uninterrupted access.'],
            'trial_expired' => ["Your {$product} trial has ended", 'Your free trial has ended. Your business information remains available in read-only mode while you choose a plan.'],
            'trial_extended' => ["Your {$product} trial was extended", 'Platform Operations extended your trial. The updated end date is now visible in Billing.'],
            'subscription_activated' => ["{$product} subscription active", 'Your subscription is active and the corresponding business capabilities are available.'],
            'cancellation_scheduled' => ['Subscription cancellation scheduled', 'Your subscription is scheduled to end at the close of the current billing period. You can reactivate it before that date.'],
            'subscription_reactivated' => ['Subscription reactivated', 'Scheduled cancellation was removed and your subscription will continue renewing normally.'],
            'subscription_canceled' => ['Subscription canceled', 'Your subscription has been canceled. Review Billing for the exact access and export dates that apply.'],
            'subscription_recovered' => ['Subscription payment recovered', 'Payment recovery succeeded and normal subscription access has been restored.'],
            'subscription_terminated' => ['Subscription ended', 'Your subscription has ended. Review Billing for the final data-export availability date.'],
            'plan_change_requested' => ['Plan change requested', 'Your requested plan change is recorded. Billing shows whether it applies immediately or at the end of the current period.'],
            'subscription_restricted' => ['Subscription access is limited', 'The payment recovery period has ended. Existing information remains readable, while protected changes are paused until billing is resolved.'],
            'renewal_retry_failed' => ['Subscription payment still needs attention', 'A renewal retry did not succeed. Update the saved payment method before the dated grace period ends.'],
            default => ['Subscription payment needs attention', 'Your subscription renewal did not succeed. We will retry automatically and keep billing and export access available.'],
        };
        $timeZone = $this->subscription->business->time_zone ?: config('app.timezone', 'UTC');

        $mail = (new MailMessage)
            ->subject($subject.' — '.$this->subscription->business->name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($message)
            ->when($this->noticeType === 'trial_ending', fn (MailMessage $message) => $message->line('Trial ends: '.$this->date($this->subscription->trial_ends_at, $timeZone)))
            ->when($this->noticeType === 'trial_extended', fn (MailMessage $message) => $message->line('New trial end: '.$this->date($this->subscription->trial_ends_at, $timeZone)))
            ->when(in_array($this->noticeType, ['renewal_failed', 'renewal_retry_failed'], true), fn (MailMessage $message) => $message->line('Grace period ends: '.$this->date($this->subscription->grace_ends_at, $timeZone)))
            ->when($this->noticeType === 'cancellation_scheduled', fn (MailMessage $message) => $message->line('Access continues through: '.$this->date($this->subscription->cancel_at, $timeZone)))
            ->when($this->noticeType === 'subscription_terminated', fn (MailMessage $message) => $message->line('Export available through: '.$this->date($this->subscription->export_available_until, $timeZone)))
            ->action('Manage billing', route('business.billing.show', $this->subscription->business));

        return $this->brandedMail($mail, 'billing', 'billing_'.$this->noticeType);
    }

    public function businessId(): ?int
    {
        return $this->subscription->business_id;
    }

    public function notificationStream(): string
    {
        return 'billing';
    }

    private function date(mixed $date, string $timeZone): string
    {
        return $date?->timezone($timeZone)->format('M j, Y \a\t g:i A T') ?? 'not scheduled';
    }
}
