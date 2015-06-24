<?php

namespace Colligator\Jobs;

use Colligator\Jobs\Job;
use Illuminate\Contracts\Bus\SelfHandling;
use Scriptotek\OaiPmh\Client as OaiPmhClient;
use Colligator\Events\OaiPmhHarvestStatus;
use Colligator\Events\OaiPmhHarvestError;
use Colligator\Collection;
use Colligator\Document;
use Illuminate\Foundation\Bus\DispatchesJobs;

class OaiPmhHarvest extends Job implements SelfHandling
{
    use DispatchesJobs;

    public $name;
    public $url;
    public $schema;
    public $set;
    public $start;
    public $until;
    public $resume;
    public $maxRetries;
    public $sleepTimeOnError;

    /**
     * Number of records retrieved between each emitted OaiPmhHarvestStatus event.
     * A too small number will cause CPU overhead
     *
     * @var int
     */
    protected $statusUpdateEvery = 50;

    /**
     * Create a new job instance.
     *
     * @param  string  $name  Harvest name from config
     * @param  array   $config  Harvest config array (url, set, schema)
     * @param  string  $start  Start date (optional)
     * @param  string  $until  End date (optional)
     * @param  string  $resume  Resumption token for continuing an aborted harvest (optional)
     */
    public function __construct($name, $config, $start = null, $until = null, $resume = null)
    {
        $this->name = $name;
        $this->url = $config['url'];
        $this->schema = $config['schema'];
        $this->set = $config['set'];
        $this->start = $start;
        $this->until = $until;
        $this->resume = $resume;
        $this->maxRetries = array_get($config, 'max-retries', 1000);
        $this->sleepTimeOnError = array_get($config, 'sleep-time-on-error', 60);
    }

    public function error($msg)
    {
        \Event::fire(new OaiPmhHarvestError($msg));
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $dest_path = storage_path('harvests/' . $this->name);
        if (!file_exists($dest_path)) mkdir($dest_path, 0777, true);
        $latest = $dest_path . '/latest.xml';

        $client = new OaiPmhClient($this->url, array(
            'schema' => $this->schema,
            'user-agent' => 'Colligator/0.1',
            'max-retries' => $this->maxRetries,
            'sleep-time-on-error' => $this->sleepTimeOnError,
        ));

        $client->on('request.error', function($msg) {
            $this->error($msg);
        });

        $collection = Collection::where('name', '=', $this->name)->first();
        if (is_null($collection)) {
            $this->error("Collection '$this->name' not found in DB");
            return;
        }

        // For each response
        $client->on('request.complete', function($verb, $args, $body) use ($latest) {
            file_put_contents($latest, $body);
        });

        $recordsHarvested = 0;

        // Loop over all records using an iterator that pulls in more data when
        // the buffer is exhausted.
        $records = $client->records($this->start, $this->until, $this->set, $this->resume);
        while (true) {

            // If no records included in the last response
            if (!$records->valid()) {
                break 1;
            }

            $record = $records->current();
            $recordsHarvested++;

            // In case of a crash, it can be useful to have the resumption_token
            if ($this->resume != $records->getResumptionToken()) {
                $this->resume = $records->getResumptionToken();
                file_put_contents($dest_path . '/resumption_token', $this->resume);
            }

            // Note that Bibsys doesn't start counting on 0, as given in the spec,
            // but it doesn't really matter since we're only interested in a
            // fixed order.
            $currentIndex = $records->key();

            // Move to stable location
            if (is_file($latest)) {
                rename($latest, $dest_path . sprintf('/response_%08d.xml', $currentIndex));
            }

            $this->dispatch(new ImportMarc21Record($record));

            // TODO: Add document to collection!

            if ($recordsHarvested % $this->statusUpdateEvery == 0) {
                if (is_null($this->start)) {
                    \Event::fire(new OaiPmhHarvestStatus($recordsHarvested, $recordsHarvested, $records->numberOfRecords));
                } else {
                    \Event::fire(new OaiPmhHarvestStatus($recordsHarvested, $currentIndex, $records->numberOfRecords));
                }
            }

            $attempt = 1;
            while (true) {
                try {
                    $records->next();
                    break 1;
                } catch (Scriptotek\Oai\BadRequestError $e) {
                    $this->error('Bad request. Attempt ' . $attempt . ' of 500. Sleeping 60 secs.');
                    if ($attempt > 500) {
                        throw $e;
                    }
                    $attempt++;
                    sleep(60);
                }
            }
        }
        return true;
    }


}
