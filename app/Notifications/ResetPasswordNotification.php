<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements NotificationStream
{
    use BuildsBrandedMail;

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return $this->brandedMail(
            (new MailMessage)
                ->subject('Reset your '.config('brand.product_name').' password')
                ->greeting('Hello '.$notifiable->name.',')
                ->line('We received a request to reset the password for your account.')
                ->action('Reset password', $this->resetUrl($notifiable))
                ->line("For your security, this link expires in {$minutes} minutes and can be used only once.")
                ->line('If you did not request this, you do not need to take any action. Your current password has not changed.'),
            'security',
            'password_reset_requested',
        )->priority(1);
    }

    public function notificationStream(): string
    {
        return 'security';
    }
}
