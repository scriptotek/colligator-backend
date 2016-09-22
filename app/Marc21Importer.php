<?php

namespace Colligator;

use Colligator\Events\Marc21RecordImported;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Event;
use Scriptotek\Marc\Record as MarcRecord;
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
        $data->registerXPathNamespaces([
            'marc' => 'http://www.loc.gov/MARC21/slim'
        ]);
        $biblio = null;
        $holdings = [];
        foreach ($data->xpath('.//marc:record') as $rec) {
            $parsed = $this->parser->parse($rec);
            if ($parsed instanceof BibliographicRecord) {
                $biblio = $parsed->toArray();
            } elseif ($parsed instanceof HoldingsRecord) {
                $holdings[] = $parsed->toArray();
            }
        }

        if (!count($holdings)) {
            // Oh, hello Alma...
            $q = $data->first('.//marc:record')->asXML();

            $rec = MarcRecord::fromString($q);

            $itemMap = [
                'x' => 'location',  // OBS: 1030310
                'y' => 'shelvinglocation',  // OBS: k00475
                'b' => 'barcode',
                'z' => 'callcode',
                'a' => 'id',
                'd' => 'due_back_date',
                'p' => 'process_type',
                's' => 'item_status',
                'n' => 'public_note',
            ];

            foreach ($rec->getFields('909') as $field) {
                $holding = [];

                foreach ($itemMap as $c => $f) {
                    $sf = $field->getSubfield($c);
                    if ($sf) {
                        $holding[$f] = $sf->getData();
                    }
                }

                $sf = $field->getSubfield('s');
                if ($sf) {
                    $sft = $sf->getData();
                    if ($sft) {
                        $holding['circulation_status'] = 'Available';
                    } else {
                        $holding['circulation_status'] = 'Unavailable';
                    }
                }

                $holdings[] = $holding;
            }
        }

        return [$biblio, $holdings];
    }

    /**
     * Fixes wrong vocabulary codes returned by the Bibsys SRU service.
     * Bibsys strips dashes in 648, 650, 655, but not in 651, so we have
     * to do that ourselves. This affects 'no-ubo-mn' and 'no-ubo-mr'.
     */
    public function fixVocabularyCode($code)
    {
        return str_replace('-', '', $code);
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

            // @TODO: https://github.com/scriptotek/colligator-backend/issues/34
            // Alma uses 776, but the IDs can't be looked up. Need to investigate!
            // Example: 990824196984702204 (NZ: 990824196984702201), having
            // 776    0_ $t The Earth after us : what legacy will humans leave in the rocks? $w 991234552274702201

            // $doc2 = Document::where('bibsys_id', '=', $other_id)->first();
            // if (is_null($doc2)) {
            //     $record = \SruClient::first('bs.objektid=' . $other_id);
            //     \Log::debug('Importing related record ' . $other_id);
            //     if (is_null($record)) {
            //         die('uh oh');
            //     } else {
            //         $this->import($record->data);
            //     }
            // }

            // @TODO: Add a separate jobb that updates e-books weekly or so..
        }

        // Sync subjects
        $subject_ids = [];
        foreach ($biblio['subjects'] as $value) {
            $value['vocabulary'] = $this->fixVocabularyCode($value['vocabulary']);
            $subject = Subject::lookup($value['vocabulary'], $value['term'], $value['type']);
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

        \Log::debug('[Marc21Importer] Imported ' . $doc->bibsys_id . ' as ' . $doc->id);

        if (!is_null($doc)) {
            Event::fire(new Marc21RecordImported($doc->id));
        }

        return $doc->id;
    }
}
