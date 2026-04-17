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
        $schedule->command('analytics:aggregate --date=' . now()->subDay()->toDateString())->dailyAt('01:10');
        $schedule->command('analytics:aggregate --date=' . now()->toDateString())->hourlyAt(10);
        $schedule->command('growth:run')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('notifications:scan-escalations')->everyTenMinutes()->withoutOverlapping();
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
