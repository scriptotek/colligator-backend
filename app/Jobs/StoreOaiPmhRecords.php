<?php

namespace Colligator\Jobs;

use Colligator\Jobs\Job;
use Illuminate\Contracts\Bus\SelfHandling;
use Scriptotek\OaiPmh\Client as OaiPmhClient;
use Colligator\Events\OaiPmhHarvestStatus;
use Colligator\Events\OaiPmhHarvestError;

class StoreOaiPmhRecords extends Job implements SelfHandling
{

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

        $client->on('request.error', function($err) {
            \Event::fire(new OaiPmhHarvestError($err));
        });

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

            if ($recordsHarvested % $this->statusUpdateEvery == 0) {
                \Event::fire(new OaiPmhHarvestStatus($recordsHarvested, $currentIndex, $records->numberOfRecords));
            }

            $attempt = 1;
            while (true) {
                try {
                    $records->next();
                    break 1;
                } catch (Scriptotek\Oai\BadRequestError $e) {
                    \Event::fire(new OaiPmhHarvestError('Bad request. Attempt ' . $attempt . ' of 500. Sleeping 60 secs.'));
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
    /**
     * Store a single record
     *
     * @return 'added', 'changed', 'unchanged' or 'removed'
     */
    public function store($record, $oaiSet)
    {
        $status = 'unchanged';
        // ex.: oai:bibsys.no:collection:901028711
        $bibsys_id = $record->data->text('.//marc:record[@type="Bibliographic"]/marc:controlfield[@tag="001"]');
        if (strlen($bibsys_id) != 9) {
            Log::error("[$record->identifier] Invalid record id: $bibsys_id");
            // $this->progress->clear();
            $this->output->writeln("\n<error>[$record->identifier] Invalid record id: $bibsys_id</error>");
            // $this->progress->display();
            return 'errored';
        }
        $doc = Document::where('bibsys_id', '=', $bibsys_id)->first();
        if (is_null($doc)) {
            Log::info(sprintf('[%s] CREATE document', $bibsys_id));
            $status = 'added';
            $doc = new Document;
            $doc->bibsys_id = $bibsys_id;
            $doc->save();
        } else {
            // Log::info(sprintf('[%s] UPDATE document', $bibsys_id));
        }
        try {
            $doc->import($record->data, $this->output);
        } catch (Exception $e) {
            Log::error("[$record->identifier] Import failed: Invalid record. Exception '" . $e->getMessage() . "' in: " . $e->getFile() . ":" . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());
               //kk var_export($e->getTrace(), true) );
            // $this->progress->clear();
            $this->output->writeln("\n<error>[$record->identifier] Import failed: Invalid record, see log for details.</error>");
            // $this->progress->display();
            return 'errored';
        }
        if (!isset($doc->sets)) {
            $doc->sets = array();
        }
        if (!in_array($oaiSet, $doc->sets)) {
            $sets = $doc->sets;
            $sets[] = $oaiSet;
            $doc->sets = $sets;
        }
        if ($status == 'unchanged' && $doc->isDirty()) {
            $status = 'changed';
            $msg = sprintf("[%s] UPDATE document\n", $bibsys_id);
            foreach ($doc->getAttributes() as $key => $val) {
                 if ($doc->isDirty($key)) {
                     $original = $doc->getOriginal($key);
                     if ($original) {
                         $current = $val;
                         $msg .= "Key: $key\n";
                         $msg .= "Old: " . json_encode($original) . "\n";
                         $msg .= "New: " . json_encode($current) . "\n";
                         $msg .= "-------------------------------------------\n";
                     }
                 }
             }
             Log::info($msg);
        }
        if (!$doc->save()) {  // No action done if record not dirty
            $err = "[$record->identifier] Document $id could not be saved!";
            Log::error($err);
            $this->output->writeln("<error>$err</error>");
            return 'errored';
        }
        return $status;
    }

}
