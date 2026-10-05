<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorSecurityNotification extends Notification implements NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public function __construct(public readonly string $change) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $message] = match ($this->change) {
            'enabled' => ['Two-factor authentication is active', 'Two-factor authentication was enabled for your account. Future sign-ins will require your authenticator code.'],
            'disabled' => ['Two-factor authentication was disabled', 'Two-factor authentication was disabled for your account. Your password is now the only sign-in factor.'],
            'recovery_codes_regenerated' => ['New recovery codes were generated', 'Your previous two-factor recovery codes were replaced. Store the new codes securely; the previous set no longer works.'],
            'recovery_code_used' => ['A recovery code was used', 'A two-factor recovery code was used to sign in. The used code has been replaced automatically.'],
            default => ['Account security changed', 'A two-factor authentication setting changed on your account.'],
        };

        return $this->brandedMail(
            (new MailMessage)
                ->subject($subject.' — '.config('brand.product_name'))
                ->greeting('Hello '.$notifiable->name.',')
                ->line($message)
                ->action('Review account security', route('profile.show'))
                ->line('If you did not make or recognise this change, reset your password and contact support immediately.'),
            'security',
            'two_factor_'.$this->change,
        )->priority(1);
    }

    public function notificationStream(): string
    {
        return 'security';
    }
}
