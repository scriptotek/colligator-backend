<?php

namespace Colligator\Console\Commands;

use Colligator\Search\DocumentsIndex;
use Colligator\Search\ElasticSearchIndex;
use Colligator\Search\EntitiesIndex;
use Illuminate\Console\Command;

class Reindex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:reindex
                            {name?  : Index name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-build one or more ElasticSearch indices.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $indices = [
            'entities' => EntitiesIndex::class,
            'documents' => DocumentsIndex::class,
        ];
        $indexName = $this->argument('name');
        if (!empty($indexName)) {
            if (!isset($indices[$indexName])) {
                $this->error("Index does not exist: {$indexName}");
                return;
            }
            $indices = [$indices[$indexName]];
        } else {
            $indices = array_values($indices);
        }

        \Log::info('[ReindexJob] Starting job.');

        $this->info('');
        $this->info(' Rebuilding the Elasticsearch index will take some time.');
        $this->info('');

        foreach ($indices as $indexCls) {
            $index = resolve($indexCls);
            $this->processIndex($index);
        }
    }

    public function processIndex(ElasticSearchIndex $index)
    {
        $t0 = microtime(true);

        $oldVersion = $index->getCurrentVersion();
        $newVersion = $oldVersion + 1;

        $this->comment(" Index: {$index->name}. Old version: {$oldVersion}. New version: {$newVersion}");

        if ($index->versionExists($newVersion)) {
            $this->comment(' New version already existed, probably from a crashed job. Removing.');
            $index->dropVersion($newVersion);
        }

        $this->comment(' Creating new index');
        $index->createVersion($newVersion);

        $this->comment(sprintf(' [%03d] Building entity usage cache', microtime(true) - $t0));
        $index->prepareReindex();

        $this->comment(sprintf(' [%03d] Filling new index...', microtime(true) - $t0));

        $model = $index->model;

        $modelCount = $model::count();
        $this->output->progressStart($modelCount);

        $index->model::with($index->modelRelationships)->chunk(1000, function ($docs) use ($index, $newVersion) {
            foreach ($docs as $doc) {
                $index->index($doc, $newVersion);
                $this->output->progressAdvance();
            }
        });

        $this->output->progressFinish();

        $this->comment(sprintf(' [%03d] Swapping indices', microtime(true) - $t0));
        $index->activateVersion($newVersion);

        $this->comment(sprintf(' [%03d] Dropping old index', microtime(true) - $t0));
        $index->dropVersion($oldVersion);

        $dt = microtime(true) - $t0;
        $this->info(" Index {$index->name} rebuilt in " . round($dt) . " seconds.");
        \Log::info("[ReindexJob] {$index->name} completed in " . round($dt) . " seconds.");
    }
}
