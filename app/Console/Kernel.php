<?php

namespace Colligator\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\OaiPmhHarvest::class,
        Commands\OaiPmhHarvestInfo::class,
        Commands\CreateCollection::class,
        Commands\Reindex::class,
        Commands\HarvestXisbn::class,
        Commands\ImportOntosaur::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('php artisan colligator:harvest-oaipmh samling42 --daily')
                 ->dailyAt('04:00');
    }
}
