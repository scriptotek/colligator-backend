<?php

namespace Colligator\Jobs;

use Colligator\Document;
use Colligator\Events\Marc21RecordImported;
use Colligator\Subject;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Event;
use Scriptotek\SimpleMarcParser\Parser as MarcParser;
use Scriptotek\SimpleMarcParser\ParserException;
use Scriptotek\SimpleMarcParser\BibliographicRecord;
use Scriptotek\SimpleMarcParser\HoldingsRecord;
use Illuminate\Contracts\Bus\SelfHandling;

class ImportMarc21Record extends Job implements SelfHandling
{

    public $record;
    public $parser;

    /**
     * Create a new job instance.
     *
     * @param $record
     */
    public function __construct(QuiteSimpleXMLElement $record = null, MarcParser $parser = null)
    {
        $this->record = $record;
        $this->parser = $parser ?: new MarcParser;
    }

     /**
     * Parse using SimpleMarcParser and separate bibliographic and holdings.
     *
     * @param QuiteSimpleXMLElement $data
     * @return array
     */
    public function parseRecord(QuiteSimpleXMLElement $data)
    {
        $biblio = null;
        $holdings = array();
        foreach ($data->xpath('.//marc:record') as $rec) {
            $parsed = $this->parser->parse($rec);
            if ($parsed instanceof BibliographicRecord) {
                $biblio = $parsed->toArray();
            } elseif ($parsed instanceof HoldingsRecord) {
                $holdings[] = $parsed->toArray();
            }
        }

        return array($biblio, $holdings);
    }

    /**
     * @param array $biblio
     * @param array $holdings
     * @return null|Document
     */
    public function import(array $biblio, array $holdings = [])
    {
        // Convert Carbon date objects to ISO8601 strings
        if (isset($biblio['created'])) {
            $biblio['created'] = $biblio['created']->toIso8601String();
        }
        if (isset($biblio['modified'])) {
            $biblio['modified'] = $biblio['modified']->toIso8601String();
        }
        foreach ($holdings as &$holding)
        {
            $holding['created'] = $holding['created']->toIso8601String();
            if (isset($holding['acquired']))
            {
                $holding['acquired'] = $holding['acquired']->toIso8601String();
            }
        }

        // Find existing Document or create a new one
        $doc = Document::firstOrNew(['bibsys_id' => $biblio['id']]);

        // Update Document
        $doc->bibliographic = $biblio;
        $doc->holdings = $holdings;

        if (!$doc->save()) {
            $this->error("Document $biblio->id could not be saved!");
            return null;
        }

        // Sync subjects
        $subject_ids = [];
        foreach ($biblio['subjects'] as $value) {
            $subject = Subject::lookup($value['vocabulary'], $value['term']);
            if (is_null($subject)) {
                $subject = Subject::create($value);
            }
            $subject_ids[] = $subject->id;
        }
        $doc->subjects()->sync($subject_ids);

        // Extract cover from bibliographic record if no local cover exists
        if (isset($biblio['cover_image']) && is_null($doc->cover)) {
            try {
                $doc->storeCover($biblio['cover_image']);
            } catch (\ErrorException $e) {
                \Log::error('Failed to store cover: ' . $biblio['cover_image']);
            }
        }

        return $doc;
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
            return;
        }

        $doc = $this->import($biblio, $holdings);

        if (!is_null($doc)) {
            Event::fire(new Marc21RecordImported($doc->id));
        }
    }

}
