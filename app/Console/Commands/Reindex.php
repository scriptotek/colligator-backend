<?php

namespace Colligator\Console\Commands;

use Colligator\Document;
use Colligator\Search\DocumentsIndex;
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
     * Return IDs of all subjects used on a collection of documents.
     *
     * @param Document[] $docs
     * @return int[]
     */
    public function getSubjectIdsForDocuments($docs)
    {
        $ids = [];
        foreach ($docs as $doc) {
            foreach ($doc->subjects as $subject) {
                $ids[] = $subject->id;
            }
        }
        return $ids;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(DocumentsIndex $docIndex)
    {
        $this->info('');
        $this->info(' Rebuilding the Elasticsearch index will take some time.');
        //$this->info(' Laravel will be put in maintenance mode.');
        $this->info('');
        // if ($this->confirm('Do you wish to continue? [Y|n]', true)) {
            //\Artisan::call('down');

        //$docIndex->dropVersion();
        $oldVersion = $docIndex->getCurrentVersion();
        $newVersion = $oldVersion + 1;
        $this->comment(' Old version: ' . $oldVersion . ', new version: ' . $newVersion);

        if ($docIndex->versionExists($newVersion)) {
            $this->comment(' New version already existed, probably from a crashed job. Removing.');
            $docIndex->dropVersion($newVersion);
        }

        $this->comment(' Creating new index');
        $docIndex->createVersion($newVersion);


        $t0 = microtime(true);

        $this->comment(' Building subject usage cache');
        $docs = Document::with('subjects', 'genres', 'cover')->get();
        $subject_ids = $this->getSubjectIdsForDocuments($docs);
        $docIndex->addToSubjectUsageCache($subject_ids);

        $this->comment(' Filling new index');
        $this->output->progressStart(Document::count());
        for ($i=count($docs) - 1; $i >= 0; $i--) {
            $docIndex->index($docs[$i], $newVersion);
            unset($docs[$i]);
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();

        $this->comment(' Swapping indices');
        $docIndex->activateVersion($newVersion);

        $this->comment(' Dropping old index');
        $docIndex->dropVersion($oldVersion);

       // \Artisan::call('up');

        $dt = microtime(true) - $t0;
        $this->info(' Completed in ' . round($dt) . ' seconds.');
    }
}
