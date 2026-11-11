<?php

namespace App\Mail\Marketplace;

use Illuminate\Mail\Mailable;

class SignInLink extends Mailable
{
    public function __construct(public readonly string $signInUrl) {}

    public function build(): static
    {
        $this->theme = 'marketplace';
        if (filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            $this->from(config('mail.from.address'), config('brand.marketplace_email_name'));
        }

        return $this->subject('Your Clipperdesk Marketplace sign-in link')->markdown('emails.marketplace.sign-in');
    }
}
