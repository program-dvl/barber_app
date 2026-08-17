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
        $schedule->command('billing:send-notices')->everyMinute()->withoutOverlapping();
        $schedule->command('communications:process-events')->everyMinute()->withoutOverlapping();
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
