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

        $this->output->progressStart(Document::count());
        foreach (Document::with('subjects', 'cover')->get() as $doc) {
            $se->indexDocument($doc);
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();
        \Artisan::call('up');
        // }
    }
}
