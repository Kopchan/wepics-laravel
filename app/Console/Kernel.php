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
        // $schedule->command('inspire')->hourly();

        // Pick up files added to storage/app/{images,users} without restarts.
        // Non-interactive flags: --layers (no sort prompt), --missing=skip (no delete prompts).
        // Production-only so local dev (DDEV) is unaffected.
        $schedule->command('app:index --layers --missing=skip')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->when(fn () => app()->isProduction());
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
