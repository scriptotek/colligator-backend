<?php

namespace Colligator\Jobs;

use Colligator\Collection;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Log;
use Scriptotek\OaiPmh\BadRequestError;
use Scriptotek\OaiPmh\Client as OaiPmhClient;
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
    public $fromDump;
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
     * @param bool   $fromDump Import from local dump
     */
    public function __construct($name, $config, $start = null, $until = null, $resume = null, $fromDump = false)
    {
        $this->name = $name;
        $this->url = $config['url'];
        $this->schema = $config['schema'];
        $this->set = $config['set'];
        $this->start = $start;
        $this->until = $until;
        $this->resume = $resume;
        $this->fromDump = $fromDump;
        $this->maxRetries = array_get($config, 'max-retries', 1000);
        $this->sleepTimeOnError = array_get($config, 'sleep-time-on-error', 60);
    }

    /**
     * Import local XML dump rather than talking to the OAI-PMH server.
     */
    public function fromDump()
    {
        $files = Storage::disk('local')->files('harvests/' . $this->name);
        $recordsHarvested = 0;
        foreach ($files as $filename) {
            if (!preg_match('/.xml$/', $filename)) {
                continue;
            }

            $response = new ListRecordsResponse(Storage::disk('local')->get($filename));
            foreach ($response->records as $record) {
                $this->dispatch(new ImportRecord($this->collection, $record->data->asXML()));
                ++$recordsHarvested;
                if ($recordsHarvested % $this->statusUpdateEvery == 0) {
                    $this->status($recordsHarvested, $recordsHarvested);
                }
            }

        }

        return $recordsHarvested;
    }

    public function fromNetwork()
    {
        $dest_path = 'harvests/' . $this->name . '/';

        Storage::disk('local')->deleteDir($dest_path);

        $latest = $dest_path . 'latest.xml';

        $client = new OaiPmhClient($this->url, [
            'schema'              => $this->schema,
            'user-agent'          => 'Colligator/0.1',
            'max-retries'         => $this->maxRetries,
            'sleep-time-on-error' => $this->sleepTimeOnError,
        ]);

        $client->on('request.error', function ($msg) {
            $this->error($msg);
        });

        // Store each response to disk just in case
        $client->on('request.complete', function ($verb, $args, $body) use ($latest) {
            Storage::disk('local')->put($latest, $body);
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
            ++$recordsHarvested;

            // In case of a crash, it can be useful to have the resumption_token,
            // but delete it when the harvest is complete
            if ($this->resume != $records->getResumptionToken()) {
                $this->resume = $records->getResumptionToken();
                if (is_null($this->resume)) {
                    Storage::disk('local')->delete($dest_path . '/resumption_token');
                } else {
                    Storage::disk('local')->put($dest_path . '/resumption_token', $this->resume);
                }
            }

            // Note that Bibsys doesn't start counting on 0, as given in the spec,
            // but it doesn't really matter since we're only interested in a
            // fixed order.
            $currentIndex = $records->key();

            // Move to stable location
            $destPath = sprintf('%s/response_%08d.xml', $dest_path, $currentIndex);
            if (Storage::disk('local')->exists($latest)) {
                Storage::disk('local')->move($latest, $destPath);
            }

            $this->dispatch(new ImportRecord($this->collection, $record->data->asXML()));

            if ($recordsHarvested % $this->statusUpdateEvery == 0) {
                if (is_null($this->start)) {
                    $this->status($recordsHarvested, $recordsHarvested);
                } else {
                    $this->status($recordsHarvested, $currentIndex);
                }
            }

            $attempt = 1;
            while (true) {
                try {
                    $records->next();
                    break 1;
                } catch (BadRequestError $e) {
                    $this->error($e->getMessage());
                    $this->error($e->getCode());
                    $this->error('Bad request. Attempt ' . $attempt . ' of 100. Sleeping 10 secs.');
                    if ($attempt > 100) {
                        throw $e;
                    }
                    ++$attempt;
                    sleep(10);
                }
            }
        }

        return $recordsHarvested;
    }

    /**
     * Execute the job.
     *
     * @throws BadRequestError
     * @throws \Exception
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

        if ($this->fromDump) {
            $recordsHarvested = $this->fromDump();
        } else {
            $recordsHarvested = $this->fromNetwork();
        }

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
