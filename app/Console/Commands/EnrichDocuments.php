<?php

namespace Colligator\Console\Commands;

use Colligator\Document;
use Colligator\EnrichmentService;
use Illuminate\Console\Command;

class EnrichDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:enrich {service} {--f|force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich documents using some service.';

    /**
     * Sleep time in seconds between requests.
     *
     * @var int
     */
    public $sleepTime = 5;

    protected $services;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->services = [
            \Colligator\GoogleBooksService::$serviceName => 'Colligator\GoogleBooksService',
        ];
    }

    public function getDocumentsToBeChecked($serviceName, $force = false)
    {
        if ($force) {
            return Document::all();
        }
        return Document::whereDoesntHave('enrichments', function($query) use ($serviceName) {
            $query->where('service_name', '=', $serviceName);
        })->get();
    }

    public function handleDocuments(EnrichmentService $service, $docs)
    {
        $this->info('Will check ' . count($docs) . ' documents using "' . $service::$serviceName . '" service');
        \Log::info('[EnrichDocuments] Starting job. ' . count($docs) . ' documents to be checked.');

        $this->output->progressStart(count($docs));
        foreach ($docs as $doc) {
            $service->enrich($doc);

            $this->output->progressAdvance();
            sleep($this->sleepTime);
        }
        $this->output->progressFinish();
        \Log::info('[EnrichDocuments] Complete.');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $serviceName = $this->argument('service');
        $force = $this->option('force');
        $verbose = $this->option('verbose');

        if (!isset($this->services[$serviceName])) {
            $this->error('Service "' . $serviceName . '" is not defined. Available servies: "' . implode('", "', array_keys($this->services)) . '".');
            return;
        }

        if ($verbose) {
            \DB::listen(function ($query, $bindings) {
                $this->comment('Query: ' . $query . '. Bindings: ' . implode(', ', $bindings));
            });
        }

        $docs = $this->getDocumentsToBeChecked($serviceName, $force);

        if (!count($docs)) {
            $this->info('No new documents. Exiting.');
            \Log::info('[EnrichDocuments] No new documents to be checked.');
        }

        $service = $this->getLaravel()->make($this->services[$serviceName]);
        $this->handleDocuments($service, $docs);
    }

}
