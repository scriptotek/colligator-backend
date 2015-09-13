<?php

namespace Colligator\Console\Commands;

use Colligator\Document;
use Colligator\XisbnClient;
use Illuminate\Console\Command;
use Log;

class HarvestXisbn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:harvest-xisbn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds additional isbn numbers from the xisbn service.';

    /**
     * Sleep time in seconds between requests.
     *
     * @var int
     */
    public $sleepTime = 5;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function getDocuments()
    {
        return Document::whereNull('xisbn')->get();
    }

    /**
     * @param XisbnClient $client
     * @param Document[]  $docs
     */
    public function handleDocuments(XisbnClient $client, $docs)
    {
        $this->info('Will check ' . count($docs) . ' documents');
        Log::info('[HarvestXisbnJob] Starting job. ' . count($docs) . ' documents to be checked.');

        $this->output->progressStart(count($docs));
        foreach ($docs as $doc) {
            $response = $client->checkIsbns($doc->bibliographic['isbns']);
            if ($response->overLimit()) {
                $this->error('Reached daily limit. Aborting.');
                break;
            }
            $doc->xisbn = $response->toArray();
            $doc->save();

            $this->output->progressAdvance();
            sleep($this->sleepTime);
        }
        $this->output->progressFinish();
        Log::info('[HarvestXisbnJob] Complete.');
    }

    /**
     * Execute the console command.
     *
     * @param XisbnClient $client
     *
     * @return void
     */
    public function handle(XisbnClient $client)
    {
        $docs = $this->getDocuments();
        if (count($docs)) {
            $this->handleDocuments($client, $docs);
        } else {
            $this->info('No new documents. Exiting.');
            Log::info('[HarvestXisbnJob] No new documents to be checked.');
        }
    }
}
