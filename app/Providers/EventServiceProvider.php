<?php

namespace App\Providers;

use App\Listeners\CompleteVerifiedOwnerOnboarding;
use App\Listeners\RecordEmailNotificationDelivery;
use App\Listeners\SendFortifySecurityNotification;
use App\Listeners\SendSignInAlert;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Verified::class => [
            CompleteVerifiedOwnerOnboarding::class,
        ],
        Login::class => [
            SendSignInAlert::class,
        ],
        TwoFactorAuthenticationConfirmed::class => [
            SendFortifySecurityNotification::class,
        ],
        TwoFactorAuthenticationDisabled::class => [
            SendFortifySecurityNotification::class,
        ],
        RecoveryCodesGenerated::class => [
            SendFortifySecurityNotification::class,
        ],
        RecoveryCodeReplaced::class => [
            SendFortifySecurityNotification::class,
        ],
        NotificationSending::class => [
            RecordEmailNotificationDelivery::class,
        ],
        NotificationSent::class => [
            RecordEmailNotificationDelivery::class,
        ],
        NotificationFailed::class => [
            RecordEmailNotificationDelivery::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
