<?php

namespace Colligator\Console\Commands;

use Colligator\Document;
use Colligator\XisbnClient;
use Illuminate\Console\Command;

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
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(XisbnClient $client)
    {
        $docs = Document::whereNull('xisbn')->get();
        if (!count($docs)) {
            $this->info('No new documents. Exiting.');
            \Log::info('[HarvestXisbnJob] No new documents to be checked.');
            return;
        }
        $this->info('Will check ' . count($docs) . ' documents');
        \Log::info('[HarvestXisbnJob] Starting job. ' . count($docs) . ' documents to be checked.');

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
            sleep(5);
        }
        $this->output->progressFinish();
        \Log::info('[HarvestXisbnJob] Complete.');
    }
}
