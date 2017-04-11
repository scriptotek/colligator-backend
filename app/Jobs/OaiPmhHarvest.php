<?php

namespace Colligator\Jobs;

use Colligator\Collection;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Log;
use Phpoaipmh\Client;
use Phpoaipmh\Endpoint;
use Scriptotek\OaiPmh\ListRecordsResponse;
use Storage;

class OaiPmhHarvest extends Job
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
     * Start time for the full harvest.
     *
     * @var float
     */
    protected $startTime;

    /**
     * Start time for the current batch.
     *
     * @var float
     */
    protected $batchTime;

    /**
     * Harvest position.
     *
     * @var int
     */
    protected $batchPos = 0;

    /**
     * @var Collection
     */
    public $collection;

    /**
     * Number of records retrieved between each emitted OaiPmhHarvestStatus event.
     * A too small number will cause CPU overhead.
     *
     * @var int
     */
    protected $statusUpdateEvery = 50;

    /**
     * Create a new job instance.
     *
     * @param string $name     Harvest name from config
     * @param array  $config   Harvest config array (url, set, schema)
     * @param string $start    Start date (optional)
     * @param string $until    End date (optional)
     * @param string $resume   Resumption token for continuing an aborted harvest (optional)
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

    public function fromNetwork()
    {
        $client = new Client($this->url);
        $endpoint = new Endpoint($client);

        $recordsHarvested = 0;

        // Loop over all records using an iterator that pulls in more data when
        // the buffer is exhausted.
        foreach ($endpoint->listRecords($this->schema, $this->start, $this->until, $this->set, $this->resume) as $record) {
            ++$recordsHarvested;

            $this->dispatch(new ImportRecord($this->collection, $record->asXML()));

            if ($recordsHarvested % $this->statusUpdateEvery == 0) {
                $this->status($recordsHarvested, $recordsHarvested);
            }
        }

        return $recordsHarvested;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info('[OaiPmhHarvest] Starting job. Requesting records from ' . ($this->start ?: '(no limit)') . ' until ' . ($this->until ?: '(no limit)') . '.');

        // For timing
        $this->startTime = $this->batchTime = microtime(true) - 1;

        $this->collection = Collection::where('name', '=', $this->name)->first();
        if (is_null($this->collection)) {
            $this->error("Collection '$this->name' not found in DB");

            return;
        }

        $recordsHarvested = $this->fromNetwork();

        Log::info('[OaiPmhHarvest] Harvest complete, got ' . $recordsHarvested . ' records.');
    }

    /**
     * Output a status message.
     *
     * @param int $fetched
     * @param int $current
     */
    public function status($fetched, $current)
    {
        $totalTime = microtime(true) - $this->startTime;
        $batchTime = microtime(true) - $this->batchTime;
        $mem = round(memory_get_usage() / 1024 / 102.4) / 10;

        $currentSpeed = ($fetched - $this->batchPos) / $batchTime;
        $avgSpeed = $fetched / $totalTime;

        $this->batchTime = microtime(true);
        $this->batchPos = $fetched;

        Log::debug(sprintf(
            '[OaiPmhHarvest] Got %d records so far - Recs/sec: %.1f (current), %.1f (avg) - Mem: %.1f MB.',
            $current,
            $currentSpeed,
            $avgSpeed,
            $mem
        ));
    }
}
