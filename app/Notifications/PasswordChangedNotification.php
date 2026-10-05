<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public int $tries = 3;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            (new MailMessage)
                ->subject('Your '.config('brand.product_name').' password was changed')
                ->greeting('Hello '.$notifiable->name.',')
                ->line('The password for your account was changed successfully.')
                ->action('Review account security', route('profile.show'))
                ->line('If you did not make this change, use the password-reset flow immediately and contact support.'),
            'security',
            'password_changed',
        )->priority(1);
    }

    public function notificationStream(): string
    {
        return 'security';
    }
}
