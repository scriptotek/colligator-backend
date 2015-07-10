<?php

namespace Colligator\Console\Commands;

use Colligator\Document;
use Colligator\SearchEngine;
use Illuminate\Console\Command;

class Reindex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:reindex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-build ElasticSearch index.';

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
    public function handle(SearchEngine $se)
    {
        $this->info('');
        $this->info(' Rebuilding the ElasticSearch index will take some time.');
        $this->info(' Laravel will be put in maintenance mode.');
        // if ($this->confirm('Do you wish to continue? [Y|n]', true)) {
            \Artisan::call('down');
        $se->dropDocumentsIndex();
        $se->createDocumentsIndex();

        $t0 = microtime(true);
        $this->output->progressStart(Document::count());
        $docs = Document::with('subjects', 'genres', 'cover')->get();
        for ($i=0; $i < count($docs); $i++) {
            $se->indexDocument($docs[$i]);
            unset($docs[$i]);
            // $mem = round(memory_get_usage() / 1024 / 102.4) / 10;
            // echo "$mem MB \n";
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();
        \Artisan::call('up');

        $dt = microtime(true) - $t0;
        $this->info('Completed in ' . round($dt) . ' seconds.');
    }
}
