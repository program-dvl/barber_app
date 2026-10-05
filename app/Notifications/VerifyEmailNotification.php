<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail implements NotificationStream
{
    use BuildsBrandedMail;

    public function toMail($notifiable): MailMessage
    {
        return $this->brandedMail(
            (new MailMessage)
                ->subject('Verify your email to start with '.config('brand.product_name'))
                ->greeting('Welcome, '.$notifiable->name.'.')
                ->line('Confirm this email address to securely create your business workspace and begin your trial.')
                ->action('Verify email address', $this->verificationUrl($notifiable))
                ->line('This link expires in '.config('auth.verification.expire', 60).' minutes.')
                ->line('If you did not create this account, you can safely ignore this email.'),
            'account',
            'email_verification',
        );
    }

    public function notificationStream(): string
    {
        return 'account';
    }
}
