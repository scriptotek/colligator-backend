<?php

namespace Colligator\Jobs;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Events\OaiPmhHarvestComplete;
use Colligator\Events\OaiPmhHarvestStatus;
use Colligator\SearchEngine;
use Event;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Scriptotek\OaiPmh\BadRequestError;
use Scriptotek\OaiPmh\Client as OaiPmhClient;
use Scriptotek\OaiPmh\ListRecordsResponse;
use Storage;

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
    public $fromDump;
    public $maxRetries;
    public $sleepTimeOnError;

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
     * @param string $name   Harvest name from config
     * @param array  $config Harvest config array (url, set, schema)
     * @param string $start  Start date (optional)
     * @param string $until  End date (optional)
     * @param string $resume Resumption token for continuing an aborted harvest (optional)
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
                $this->dispatch(new ImportMarc21Record($record->data));
                ++$recordsHarvested;
                if ($recordsHarvested % $this->statusUpdateEvery == 0) {
                    Event::fire(new OaiPmhHarvestStatus($recordsHarvested, $recordsHarvested, $response->numberOfRecords));
                }
            }
        }
        Event::fire(new OaiPmhHarvestComplete($recordsHarvested));
    }

    /**
     * Execute the job.
     */
    public function handle(SearchEngine $searchEngine)
    {
        $dest_path = 'harvests/' . $this->name . '/';

        $collection = Collection::where('name', '=', $this->name)->first();
        if (is_null($collection)) {
            $this->error("Collection '$this->name' not found in DB");

            return;
        }

        Event::listen('Colligator\Events\Marc21RecordImported', function ($event) use ($collection, $searchEngine) {
            $doc = Document::with('subjects', 'cover')->find($event->id);
            if (!$collection->documents->contains($doc->id)) {
                $collection->documents()->attach($doc->id);
            }

            // Add/update ElasticSearch
            $searchEngine->indexDocument($doc);
        });

        if ($this->fromDump) {
            $this->fromDump();

            return;
        }

        Storage::disk('local')->deleteDir($dest_path);

        $latest = $dest_path . 'latest.xml';

        $client = new OaiPmhClient($this->url, array(
            'schema' => $this->schema,
            'user-agent' => 'Colligator/0.1',
            'max-retries' => $this->maxRetries,
            'sleep-time-on-error' => $this->sleepTimeOnError,
        ));

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
            if (Storage::disk('local')->exists($latest)) {
                Storage::disk('local')->move($latest, sprintf('%s/response_%08d.xml', $dest_path, $currentIndex));
            }

            $this->dispatch(new ImportMarc21Record($record->data));

            if ($recordsHarvested % $this->statusUpdateEvery == 0) {
                if (is_null($this->start)) {
                    Event::fire(new OaiPmhHarvestStatus($recordsHarvested, $recordsHarvested, $records->numberOfRecords));
                } else {
                    Event::fire(new OaiPmhHarvestStatus($recordsHarvested, $currentIndex, $records->numberOfRecords));
                }
            }

            $attempt = 1;
            while (true) {
                try {
                    $records->next();
                    break 1;
                } catch (BadRequestError $e) {
                    $this->error('Bad request. Attempt ' . $attempt . ' of 500. Sleeping 60 secs.');
                    if ($attempt > 500) {
                        throw $e;
                    }
                    ++$attempt;
                    sleep(60);
                }
            }
        }
        Event::fire(new OaiPmhHarvestComplete($recordsHarvested));
    }
}
