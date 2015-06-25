<?php

namespace Colligator\Console\Commands;

use Danmichaelo\QuiteSimpleXMLElement\InvalidXMLException;
use Illuminate\Console\Command;
use Scriptotek\OaiPmh\ListRecordsResponse;
use Scriptotek\SimpleMarcParser\Parser as MarcParser;
use Scriptotek\SimpleMarcParser\BibliographicRecord;
use Scriptotek\SimpleMarcParser\HoldingsRecord;
use Storage;
use Symfony\Component\Console\Input\InputArgument;

class OaiPmhHarvestInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harvest:info {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::REQUIRED, 'Name of the harvest, as defined in configs/oaipmh.php'),
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $parser = new MarcParser;

        $files = Storage::disk('local')->files('harvests/' . $name);

        $info = [
            'count' => 0,
            'isbn_count' => [],
            'holdings_count' => [],
            'local_holdings_count' => [],
        ];

        $this->output->write('Reading files...');
        $this->output->progressStart(count($files));
        foreach ($files as $filename)
        {
            $this->output->progressAdvance();

            if (!preg_match('/.xml$/', $filename)) continue;

            try {

                $response = new ListRecordsResponse(Storage::disk('local')->get($filename));
                foreach ($response->records as $record) {

                    $biblio = null;
                    $holdingsCount = 0;
                    $localHoldingsCount = 0;
                    foreach ($record->data->xpath('.//marc:record') as $rec) {
                        $parsed = $parser->parse($rec);

                        if ($parsed instanceof BibliographicRecord) {
                            $biblio = $parsed;
                            $info['count'] = array_get($info, 'count', 0) + 1;
                        } elseif ($parsed instanceof HoldingsRecord) {
                            $holdingsCount++;
                            if ($parsed->location == 'UBO' && $parsed->sublocation == 'UREAL') {
                                $localHoldingsCount++;
                            }
                        }
                    }

                    if ($holdingsCount > 10) $holdingsCount = 10;
                    if ($localHoldingsCount > 10) $localHoldingsCount = 10;
                    $info['holdings_count'][$holdingsCount] = array_get($info, 'holdings_count.' . $holdingsCount, 0) + 1;
                    $info['local_holdings_count'][$localHoldingsCount] = array_get($info, 'local_holdings_count.' . $localHoldingsCount, 0) + 1;

                    if (is_null($biblio)) {
                        $this->error('No bibliographic records!');
                    } else {
                        $c = count($biblio->isbns);
                        $info['isbn_count'][$c] = array_get($info, 'isbn_count.' . $c, 0) + 1;
                    }

                }
            } catch (InvalidXMLException $e) {
                $this->error('Invalid XML found! Skipping file: ' . $filename);
            }
        }
        $this->output->progressFinish();

        $this->comment('Antall dokumenter: ' . $info['count']);

        $keys = array_keys($info['isbn_count']);
        sort($keys);
        $this->comment('Antall ISBN-numre per dokument:');
        foreach ($keys as $key) {
            $this->comment('  - ' . $key . ': ' . $info['isbn_count'][$key]);
        }

        $keys = array_keys($info['local_holdings_count']);
        sort($keys);
        $this->comment('Antall lokale holdings per dokument:');
        foreach ($keys as $key) {
            $val = $info['local_holdings_count'][$key];
            if ($key == 10) $key = '>= 10';
            $this->comment('  - ' . $key . ': ' . $val);
        }

    }
}
