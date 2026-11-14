<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('billing:advance-lifecycle')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('billing:reconcile-provider-events')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('billing:reconcile-stripe-checkouts')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('billing:send-notices')->everyMinute()->withoutOverlapping();
        $schedule->command('communications:process-events')->everyMinute()->withoutOverlapping();
        $schedule->command('marketplace:growth-reconcile')->everyMinute()->withoutOverlapping();
        $schedule->command('marketplace:growth-prune')->daily()->withoutOverlapping();
        $schedule->command('marketplace:message-email-sweep')->everyMinute()->withoutOverlapping();
        $schedule->command('marketplace:prune-login-links')->daily()->withoutOverlapping();
        $schedule->command('marketplace:prune-launch-updates')->daily()->withoutOverlapping();
        $schedule->command('account-email:prune-login-activity')->daily()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
