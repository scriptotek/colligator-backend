<?php

namespace Colligator;

use Colligator\Document;
use Colligator\Events\Marc21RecordImported;
use Colligator\Subject;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Event;
use Scriptotek\SimpleMarcParser\BibliographicRecord;
use Scriptotek\SimpleMarcParser\HoldingsRecord;
use Scriptotek\SimpleMarcParser\Parser as MarcParser;
use Scriptotek\SimpleMarcParser\ParserException;

class Marc21Importer
{
    public $record;
    public $parser;

    /**
     * Create a new job instance.
     *
     * @param $record
     */
    public function __construct(MarcParser $parser = null, DescriptionScraper $scraper)
    {
        $this->parser = $parser ?: new MarcParser();
        $this->scraper = $scraper ?: new DescriptionScraper();
    }

    /**
     * Parse using SimpleMarcParser and separate bibliographic and holdings.
     *
     * @param QuiteSimpleXMLElement $data
     *
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
     *
     * @return null|Document
     */
    public function importParsedRecord(array $biblio, array $holdings = [])
    {
        // Convert Carbon date objects to ISO8601 strings
        if (isset($biblio['created'])) {
            $biblio['created'] = $biblio['created']->toIso8601String();
        }
        if (isset($biblio['modified'])) {
            $biblio['modified'] = $biblio['modified']->toIso8601String();
        }
        foreach ($holdings as &$holding) {
            $holding['created'] = $holding['created']->toIso8601String();
            if (isset($holding['acquired'])) {
                $holding['acquired'] = $holding['acquired']->toIso8601String();
            }
        }

        // Find existing Document or create a new one
        $doc = Document::firstOrNew(['bibsys_id' => $biblio['id']]);

        // Update Document
        $doc->bibliographic = $biblio;
        $doc->holdings = $holdings;

        // Extract description from bibliographic record if no description exists
        if (isset($biblio['description']) && is_null($doc->description)) {
            $this->scraper->updateDocument($doc, $biblio['description']);
        }

        if (!$doc->save()) {
            $this->error("Document $biblio->id could not be saved!");

            return;
        }

        // Check other form
        $other_id = array_get($biblio, 'other_form.id');
        if (!empty($other_id)) {
            // TODO: Add a separate jobb that updates e-books weekly or so..
            $doc2 = Document::where('bibsys_id', '=', $other_id)->first();
            if (is_null($doc2)) {
                $record = \SruClient::first('bs.objektid=' . $other_id);
                \Log::debug('Importing related record ' . $other_id);
                if (is_null($record)) {
                    die("uh oh");
                } else {
                    $this->import($record->data);
                }
            }
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

        // Sync genres
        $genre_ids = [];
        foreach ($biblio['genres'] as $value) {
            $genre = Genre::lookup($value['vocabulary'], $value['term']);
            if (is_null($genre)) {
                $genre = Genre::create($value);
            }
            $genre_ids[] = $genre->id;
        }
        $doc->genres()->sync($genre_ids);

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
     */
    public function import(QuiteSimpleXMLElement $record)
    {

        try {
            list($biblio, $holdings) = $this->parseRecord($record);
        } catch (ParserException $e) {
            $this->error('Failed to parse MARC record. Error "' . $e->getMessage() . '" in: ' . $e->getFile() . ':' . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());

            return;
        }

        $doc = $this->importParsedRecord($biblio, $holdings);

        if (!is_null($doc)) {
            Event::fire(new Marc21RecordImported($doc->id));
        }
        return $doc->id;
    }
}
