<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\TwoFactorSecurityNotification;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class SendFortifySecurityNotification
{
    public function handle(
        TwoFactorAuthenticationConfirmed|TwoFactorAuthenticationDisabled|RecoveryCodesGenerated|RecoveryCodeReplaced $event
    ): void {
        if (! $event->user instanceof User) {
            return;
        }

        $change = match (true) {
            $event instanceof TwoFactorAuthenticationConfirmed => 'enabled',
            $event instanceof TwoFactorAuthenticationDisabled => 'disabled',
            $event instanceof RecoveryCodesGenerated => 'recovery_codes_regenerated',
            default => 'recovery_code_used',
        };

        $event->user->notify(new TwoFactorSecurityNotification($change));
    }
}
