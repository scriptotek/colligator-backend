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
        $schedule->command('colligator:harvest-oaipmh samling42 --daily')
                 ->dailyAt('02:00');

        // Bring subject heading usage counts up-to-date
        $schedule->command('colligator:reindex')
                 ->weekly()->sundays()->at('04:00');

        // Check new documents for xisbn
        $schedule->command('colligator:harvest-xisbn')
                 ->weekly()->saturdays()->at('04:00');
    }
}
