<?php

namespace Colligator\Console\Commands;

use Colligator\Document;
use Colligator\Genre;
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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(DocumentsIndex $docIndex)
    {
        $t0 = microtime(true);

        \Log::info('[ReindexJob] Starting job.');

        $this->info('');
        $this->info(' Rebuilding the Elasticsearch index will take some time.');
        $this->info('');

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

        $this->comment(sprintf(' [%03d] Building entity usage cache', microtime(true) - $t0));
        $docIndex->buildCompleteUsageCache();

        $this->comment(sprintf(' [%03d] Filling new index...', microtime(true) - $t0));

        $docCount = Document::count();
        $this->output->progressStart($docCount);

        Document::with('subjects', 'genres', 'cover')->chunk(1000, function ($docs) use ($docIndex, $newVersion) {
            foreach ($docs as $doc) {
                $docIndex->index($doc, $newVersion);
                $this->output->progressAdvance();
            }
        });
        $this->output->progressFinish();

        $this->comment(sprintf(' [%03d] Swapping indices', microtime(true) - $t0));
        $docIndex->activateVersion($newVersion);

        $this->comment(sprintf(' [%03d] Dropping old index', microtime(true) - $t0));
        $docIndex->dropVersion($oldVersion);

        $dt = microtime(true) - $t0;
        $this->info(' Completed in ' . round($dt) . ' seconds.');
        \Log::info('[ReindexJob] Completed in ' . round($dt) . ' seconds.');
    }
}
