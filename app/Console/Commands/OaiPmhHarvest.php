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
                            {--from-dump : Just re-index from dump}
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
        if ($this->option('from-dump')) {
            if ($this->option('from') || $this->option('until') || $this->option('resume') || $this->option('daily')) {
                $this->error('--from-dump cannot be combined with other options.');

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

        if ($this->option('from-dump')) {
            $this->comment(' - From local dump');
        } else {
            $this->comment(' - Repo: ' . $harvestConfig['url']);
            $this->comment(' - Schema: ' . $harvestConfig['schema']);
            $this->comment(' - Set: ' . $harvestConfig['set']);

            foreach (['from', 'until', 'resume', 'daily'] as $key) {
                if (!is_null($this->option($key))) {
                    $this->comment(sprintf(' - %s: %s', ucfirst($key), $this->option($key)));
                }
            }
        }

        $from = $this->option('from');
        $until = $this->option('until');
        if ($this->option('daily')) {
            $from = Carbon::now()->subDay()->toDateString();
            $until = $from;
        }

        $this->dispatch(
            new OaiPmhHarvestJob(
                $harvestName,
                $harvestConfig,
                $from,
                $until,
                $this->option('resume'),
                $this->option('from-dump')
            )
        );
    }
}
