<?php

namespace App\Filament\Auth;

use App\Notifications\AdminVerifyEmailNotification;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class AdminEmailVerificationPrompt extends EmailVerificationPrompt
{
    protected function sendEmailVerificationNotification(MustVerifyEmail $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->notify(new AdminVerifyEmailNotification);
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Verify your email address to continue into ClipperDesk internal administration. Send a verification email, then open its link in this browser.'),
            $this->resendNotificationAction->label('Send verification email'),
        ]);
    }
}
