<?php

namespace App\Mail\Marketplace;

use Illuminate\Mail\Mailable;

class LaunchUpdate extends Mailable
{
    public function __construct(public readonly string $area, public readonly string $actionUrl, public readonly string $unsubscribeUrl, public readonly ?string $businessName = null, public readonly bool $welcome = false) {}

    public function build(): static
    {
        $this->theme = 'marketplace';
        $this->from(config('mail.from.address'), config('brand.marketplace_email_name'));

        return $this->subject($this->businessName ? 'A new business to explore on Clipperdesk' : ($this->welcome ? 'You’re on the list | Clipperdesk Marketplace' : 'Confirm your Marketplace area updates'))->markdown('emails.marketplace.launch-update');
    }
}
