<?php

namespace Colligator\Console\Commands;

use Illuminate\Console\Command;
use Colligator\Commands\StoreOaiPmhRecords;


class HarvestOaiPmh extends Command
{

    /**
     * Start time for the full harvest.
     *
     * @var string
     */
    protected $t0;

    /**
     * Start time for the current batch.
     *
     * @var string
     */
    protected $t1;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harvest:oaipmh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Harvest records from OAI-PMH service and store as XML files.';

    /**
     * Create a new command instance.
     *
     * @return void
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
            array('name', InputArgument::REQUIRED, 'Name of the harvest, as defined in configs/oaipmh.php'),
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
            array('from', null, InputOption::VALUE_REQUIRED, 'From date (YYYY-MM-DD)'),
            array('until', null, InputOption::VALUE_REQUIRED, 'Until date (YYYY-MM-DD)'),
            array('resume', null, InputOption::VALUE_REQUIRED, 'Resumption token'),
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        // The query log is kept in memory, so we should disable it for long-running
        // tasks to prevent memory usage from increasing linearly over time
        \DB::connection()->disableQueryLog();

        $harvestName = $this->argument('name');
        $harvestConfig = Config::get('oaipmh.' . $harvestName, null);
        if (is_null($harvestConfig)) {
            $this->error('Uh oh, unknown harvest specified.');
            die;
        }

        $this->info('');
        $this->info('============================================================');
        $this->info(sprintf('%s: Starting OAI harvest "%s"',
            strftime('%Y-%m-%d %H:%M:%S'),
            $harvestName
        ));
        foreach (array('from', 'until', 'resume') as $key) {
            if (!is_null($this->option($key))) {
                $this->info(sprintf('- %s: %s', $key, $this->option($key)));
            }
        }
        $this->info('------------------------------------------------------------');

        // For timing
        $this->t0 = $this->t1 = microtime(true) - 1;

        \Event::listen('App\Events\OaiPmhHarvestStatus', function($event)
        {
            $this->info('Yo!');
        });

        $this->dispatch(
            new StoreOaiPmhRecords(
                $harvestName,
                $harvestConfig,
                $this->option('from'),
                $this->option('until'),
                $this->option('resume')
            )
        );
    }

    public function status($current, $total)
    {
        $batch = 1000;
        if ($recordsHarvested % $batch == 0) {
            // Time for a status update                
            $dt = microtime(true) - $this->t1;
            $dt2 = microtime(true) - $this->t0;
            $mem = round(memory_get_usage()/1024/102.4)/10;
            $this->t1 = microtime(true);
            $percentage = $current / $total;
            $eta = '';  
            if ($percentage < 1.0) {
                $et = $dt2 / $percentage - $dt2;
                $h = floor($et / 3600);
                $m = floor(($et - ($h * 3600)) / 60);
                $s = round($et - $h * 3600 - $m * 60);
                $eta = 'ETA: ' . sprintf("%02d:%02d:%02d", $h, $m, $s) . ', ';
            }
            $recsPerSecCur = $batch/$dt;
            $recsPerSec = $recordsHarvested / $dt2;
            $this->info(sprintf(
                '%s %d / %d records (%.2f %%), %sCurrent speed: %.1f recs/s, Avg speed: %.1f recs/s, Mem: %.1f MB.',
                strftime('%Y-%m-%d %H:%M:%S'),
                $current,
                $total,
                $percentage * 100,
                $eta,
                $recsPerSecCur,
                $recsPerSec,
                $mem
            ));
        }
    }

}
