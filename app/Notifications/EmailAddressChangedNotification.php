<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailAddressChangedNotification extends Notification implements NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public function __construct(
        public readonly string $accountName,
        public readonly string $newEmailMasked,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            (new MailMessage)
                ->subject('Your '.config('brand.product_name').' email address was changed')
                ->greeting('Hello '.$this->accountName.',')
                ->line("The sign-in email for your account was changed to {$this->newEmailMasked}.")
                ->action('Secure my account', route('password.request'))
                ->line('If you made this change, no action is needed. If you did not, reset your password immediately and contact support.'),
            'security',
            'email_address_changed',
        )->priority(1);
    }

    public function notificationStream(): string
    {
        return 'security';
    }
}
