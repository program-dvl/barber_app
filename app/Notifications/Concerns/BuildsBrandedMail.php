<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

trait BuildsBrandedMail
{
    protected function brandedMail(MailMessage $message, string $stream, string $notificationType): MailMessage
    {
        $sender = (array) config("account-notifications.senders.{$stream}", []);
        $replyTo = (array) config('account-notifications.reply_to', []);

        $message->viewData = array_merge($message->viewData, [
            'brandStream' => $stream,
        ]);

        if (blank($message->salutation)) {
            $message->salutation('The ClipperDesk team');
        }

        if (filter_var($sender['address'] ?? null, FILTER_VALIDATE_EMAIL)) {
            $message->from($sender['address'], $sender['name'] ?? config('brand.product_name'));
        }

        if (filter_var($replyTo['address'] ?? null, FILTER_VALIDATE_EMAIL)) {
            $message->replyTo($replyTo['address'], $replyTo['name'] ?? null);
        }

        return $message
            ->theme('clipperdesk')
            ->tag($stream)
            ->metadata('notification', Str::limit(Str::snake($notificationType), 128, ''));
    }

    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['mail' => (string) config('account-notifications.queue', 'emails')];
    }
}
