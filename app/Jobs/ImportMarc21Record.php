<?php

namespace Colligator\Jobs;

use Colligator\Document;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Scriptotek\SimpleMarcParser\Parser as MarcParser;
use Scriptotek\SimpleMarcParser\ParserException;
use Scriptotek\SimpleMarcParser\BibliographicRecord;
use Scriptotek\SimpleMarcParser\HoldingsRecord;
use Illuminate\Contracts\Bus\SelfHandling;
use Colligator\Jobs\Job;

class ImportMarc21Record extends Job implements SelfHandling
{

    public $record;

    /**
     * Create a new job instance.
     *
     * @param $record
     */
    public function __construct($record)
    {
        $this->record = $record;
    }

     /**
     * Parse using SimpleMarcParser and separate bibliographic and holdings.
     *
     * @param QuiteSimpleXMLElement $data
     * @return array
     */
    public function parseRecord(QuiteSimpleXMLElement $data)
    {
        $parser = new MarcParser;
        $biblio = null;
        $holdings = array();
        foreach ($data->xpath('.//marc:record') as $rec) {
            $parsed = $parser->parse($rec);
            if ($parsed instanceof BibliographicRecord) {
                $biblio = $parsed->toArray();
            } elseif ($parsed instanceof HoldingsRecord) {
                $holdings[] = $parsed->toArray();
            }
        }

        return array($biblio, $holdings);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            list($biblio, $holdings) = $this->parseRecord($this->record);
        } catch (ParserException $e) {
            $this->error('Failed to parse MARC record. Error "' . $e->getMessage() . '" in: ' . $e->getFile() . ':' . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());
            return false;
        }

        // Find existing Document or create a new one
        $doc = Document::where('bibsys_id', '=', $biblio['id'])->first();
        if (is_null($doc)) {
            // \Log::info(sprintf('[%s] CREATE document', $biblio->id));
            $doc = new Document;
        }

        $doc->bibliographic = $biblio;
        $doc->bibsys_id = $biblio['id'];
        $doc->holdings = $holdings;

        if (!$doc->save()) {  // No action done if record not dirty
            $err = "Document $biblio->id could not be saved!";
            $this->error($err);
            //$this->output->writeln("<error>$err</error>");
            return 'errored';
        }

    }
}
