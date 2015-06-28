<?php

namespace Colligator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Colligator\Jobs\OaiPmhHarvest as OaiPmhHarvestJob;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class OaiPmhHarvest extends Command
{
    use DispatchesJobs;

    /**
     * Start time for the full harvest.
     *
     * @var int
     */
    protected $startTime;

    /**
     * Start time for the current batch.
     *
     * @var int
     */
    protected $batchTime;

    /**
     * Harvest position.
     *
     * @var int
     */
    protected $batchPos = 0;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:harvest-oaipmh
                            {name?       : Name of the harvest config defined in the config file}
                            {--from=     : Start date on ISO format YYYY-MM-DD}
                            {--until=    : End date on ISO format YYYY-MM-DD}
                            {--resume=   : Resumption token}
                            {--from-dump : Just re-index from dump}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Harvest records from OAI-PMH service and store as XML files.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::OPTIONAL, 'Name of the harvest, as defined in configs/oaipmh.php'),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('from', null, InputOption::VALUE_OPTIONAL, 'From date (YYYY-MM-DD)'),
            array('until', null, InputOption::VALUE_OPTIONAL, 'Until date (YYYY-MM-DD)'),
            array('resume', null, InputOption::VALUE_OPTIONAL, 'Resumption token'),
            array('from-dump', null, InputOption::VALUE_OPTIONAL, 'Re-index from local dump'),
        );
    }

    /**
     * Output a list of the configurations.
     */
    public function listConfigurations()
    {
        $this->comment('');
        $this->comment('Available configurations:');
        $config = \Config::get('oaipmh.harvests', null);
        foreach (array_keys($config) as $key) {
            $this->comment(' - ' . $key);
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        if (is_null($name)) {
            $this->listConfigurations();
            return;
        }

        // The query log is kept in memory, so we should disable it for long-running
        // tasks to prevent memory usage from increasing linearly over time
        \DB::connection()->disableQueryLog();

        $harvestName = $this->argument('name');
        $harvestConfig = \Config::get('oaipmh.harvests.' . $harvestName, null);
        if (is_null($harvestConfig)) {
            $this->error('Unknown configuration specified.');
            $this->listConfigurations();
            return;
        }

        $this->comment('');
        $this->info(sprintf('[%s] Starting harvest "%s"',
            strftime('%Y-%m-%d %H:%M:%S'),
            $harvestName
        ));

        if ($this->option('from-dump')) {

            $this->comment(' - From local dump');

        } else {

            $this->comment(' - Repo: ' . $harvestConfig['url']);
            $this->comment(' - Schema: ' . $harvestConfig['schema']);
            $this->comment(' - Set: ' . $harvestConfig['set']);

            foreach (array('from', 'until', 'resume') as $key) {
                if (!is_null($this->option($key))) {
                    $this->comment(sprintf(' - %s: %s', ucfirst($key), $this->option($key)));
                }
            }
        }

        // For timing
        $this->startTime = $this->batchTime = microtime(true) - 1;

        \Event::listen('Colligator\Events\OaiPmhHarvestStatus', function($event)
        {
            $this->status($event->harvested, $event->position, $event->total);
        });

        \Event::listen('Colligator\Events\OaiPmhHarvestComplete', function($event)
        {
            $this->info(sprintf('[%s] Harvest complete, got %d records',
                strftime('%Y-%m-%d %H:%M:%S'),
                $event->count
            ));
        });

        \Event::listen('Colligator\Events\JobError', function($event)
        {
            $this->error($event->msg);
        });

        $this->dispatch(
            new OaiPmhHarvestJob(
                $harvestName,
                $harvestConfig,
                $this->option('from'),
                $this->option('until'),
                $this->option('resume'),
                $this->option('from-dump')
            )
        );
    }

    /**
     * Output a status message.
     *
     * @param $fetched
     * @param $current
     * @param $total
     */
    public function status($fetched, $current, $total)
    {
        $totalTime = microtime(true) - $this->startTime;
        $batchTime = microtime(true) - $this->batchTime;
        $mem = round(memory_get_usage()/1024/102.4)/10;
        $percentage = $current / $total;
        $remaining = $total - $current;

        $currentSpeed = ($fetched - $this->batchPos) / $batchTime;
        $avgSpeed = $fetched / $totalTime;

        $this->batchTime = microtime(true);
        $this->batchPos = $fetched;

        $eta = '';
        if ($remaining > 0) {   # Can be negative

            $et = $remaining / $avgSpeed;
            $h = floor($et / 3600);
            $m = floor(($et - ($h * 3600)) / 60);
            $s = round($et - $h * 3600 - $m * 60);
            $eta = 'ETA: ' . sprintf("%02d:%02d:%02d", $h, $m, $s) . ', ';
        }
        $this->comment(sprintf(
            '[%s] %d / %d records (%.2f %%) - %sRecs/sec: %.1f (current), %.1f (avg) - Mem: %.1f MB.',
            strftime('%Y-%m-%d %H:%M:%S'),
            $current,
            $total,
            $percentage * 100,
            $eta,
            $currentSpeed,
            $avgSpeed,
            $mem
        ));
    }

}
