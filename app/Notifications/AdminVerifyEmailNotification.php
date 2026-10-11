<?php

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Notifications\Messages\MailMessage;

class AdminVerifyEmailNotification extends VerifyEmailNotification
{
    public function notificationStream(): string
    {
        return 'security';
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->brandedMail(
            (new MailMessage)
                ->subject('Verify your ClipperDesk administrator email')
                ->greeting('Hello, '.$notifiable->name.'.')
                ->line('Confirm your email address to complete your internal administrator account setup.')
                ->action('Verify administrator email', Filament::getPanel('admin')->getVerifyEmailUrl($notifiable))
                ->line('This link expires in '.config('auth.verification.expire', 60).' minutes.'),
            'security',
            'admin_email_verification',
        );
    }
}
