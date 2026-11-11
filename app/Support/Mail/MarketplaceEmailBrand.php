<?php

namespace App\Support\Mail;

use Illuminate\Mail\Message;
use Illuminate\Mail\TextMessage;
use Symfony\Component\Mime\Email;

final class MarketplaceEmailBrand
{
    public const FILENAME = 'clipperdesk-marketplace-mark.png';

    public const SOURCE = 'cid:'.self::FILENAME;

    private function path(): string
    {
        return public_path('images/brand/'.self::FILENAME);
    }

    public function source(Message|TextMessage|null $message = null): ?string
    {
        if ($message instanceof TextMessage || ! is_file($this->path())) {
            return null;
        }

        // Laravel replaces this with a data URI for local previews and retains a
        // related MIME image on delivery. No remote asset fetch is required.
        if ($message) {
            foreach ($message->getSymfonyMessage()->getAttachments() as $part) {
                if ($part->getFilename() === self::FILENAME && $part->getDisposition() === 'inline') {
                    return 'cid:'.$part->getContentId();
                }
            }

            return $message->embed($this->path());
        }

        return self::SOURCE;
    }

    public function attach(Email $email): void
    {
        if (str_contains($email->getHtmlBody() ?? '', self::SOURCE) && is_file($this->path())) {
            $email->embedFromPath($this->path(), self::FILENAME, 'image/png');
        }
    }
}
