<?php

namespace Colligator\Console\Commands;

use Carbon\Carbon;
use Colligator\Jobs\OaiPmhHarvest as OaiPmhHarvestJob;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;

class OaiPmhHarvest extends Command
{
    use DispatchesJobs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:harvest-oaipmh
                            {name?       : Name of the harvest config as defined in configs/oaipmh.php}
                            {--from=     : Start date on ISO format YYYY-MM-DD}
                            {--until=    : End date on ISO format YYYY-MM-DD}
                            {--resume=   : Resumption token}
                            {--daily     : Harvest records modified yesterday. Cannot be combined with --from / --until}';

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

    public function validate()
    {
        if (empty($this->argument('name'))) {
            $this->listConfigurations();

            return false;
        }
        $harvestConfig = \Config::get('oaipmh.harvests.' . $this->argument('name'), null);
        if (is_null($harvestConfig)) {
            $this->error('Unknown configuration specified.');
            $this->listConfigurations();

            return false;
        }
        if ($this->option('daily')) {
            if ($this->option('from') || $this->option('until')) {
                $this->error('--daily cannot be combined with --from / --until.');

                return false;
            }
        }
        if ($this->option('from')) {
            if (!preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $this->option('from'))) {
                $this->error('--from must be on ISO-format YYYY-MM-DD.');

                return false;
            }
        }
        if ($this->option('until')) {
            if (!preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $this->option('until'))) {
                $this->error('--until must be on ISO-format YYYY-MM-DD.');

                return false;
            }
        }

        return true;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->validate()) {
            return;
        }

        $harvestName = $this->argument('name');
        $harvestConfig = \Config::get('oaipmh.harvests.' . $harvestName, null);

        $this->comment(sprintf('Dispatching new harvest job'));

        $this->comment(' - Repo: ' . $harvestConfig['url']);
        $this->comment(' - Schema: ' . $harvestConfig['schema']);
        $this->comment(' - Set: ' . $harvestConfig['set']);

        if ($this->option('daily')) {
            $from = Carbon::now()->setTime(0,0,0)->subDay();
            $until = Carbon::now()->setTime(0,0,0);
        } else {
            $from = $this->option('from') ? Carbon::parse($this->option('from')) : null;
            $until = $this->option('until') ? Carbon::parse($this->option('until')) : null;
        }
        $this->comment(' - From: ' . ($from ? $from->toDateTimeString() : '(unspecified)'));
        $this->comment(' - Until: ' . ($until ? $until->toDateTimeString() : '(unspecified)'));

        $this->dispatch(
            new OaiPmhHarvestJob(
                $harvestName,
                $harvestConfig,
                $from,
                $until,
                $this->option('resume')
            )
        );
    }
}
