<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingMessageNotification extends Notification implements BusinessScopedNotification, NotificationStream
{
    public function __construct(public readonly int $tenantId, public readonly string $businessName, public readonly string $conversationUrl, public readonly bool $toBusiness) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function businessId(): ?int
    {
        return $this->tenantId;
    }

    public function notificationStream(): string
    {
        return $this->toBusiness ? 'account' : 'marketplace';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->toBusiness ? 'New customer message' : 'New message from '.$this->businessName)
            ->theme($this->toBusiness ? 'clipperdesk' : 'marketplace')
            ->markdown('emails.marketplace.message-alert', ['toBusiness' => $this->toBusiness, 'businessName' => $this->businessName, 'conversationUrl' => $this->conversationUrl]);
        $sender = $this->toBusiness ? config('account-notifications.senders.account') : ['address' => config('mail.from.address'), 'name' => config('brand.marketplace_email_name')];
        if (filter_var($sender['address'] ?? null, FILTER_VALIDATE_EMAIL)) {
            $mail->from($sender['address'], $sender['name'] ?? null);
        }

        return $mail->tag($this->notificationStream())->metadata('notification', 'booking_message');
    }
}
