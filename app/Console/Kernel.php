<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // ---- Koha ILS sync (only runs when Koha is configured) ----
        if (get_settings('koha_base_url')) {
            // circulation + fines: frequent, so borrowed books & fines stay fresh
            $schedule->command('koha:sync-circulation')->everyFifteenMinutes()->withoutOverlapping()->runInBackground();
            $schedule->command('koha:sync-fines')->everyFifteenMinutes()->withoutOverlapping()->runInBackground();
            // catalog changes made in Koha: hourly reverse mirror
            $schedule->command('koha:sync-catalog')->hourly()->withoutOverlapping()->runInBackground();
            // patrons: heavy + change rarely, run nightly
            $schedule->command('koha:sync-patrons')->dailyAt('01:00')->withoutOverlapping()->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
