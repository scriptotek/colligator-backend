<?php

namespace Colligator;

use Colligator\Events\Marc21RecordImported;
use Colligator\Exceptions\CannotFetchCover;
use Event;
use Scriptotek\Marc\BibliographicRecord;
use Scriptotek\Marc\Record as MarcRecord;


class Marc21Importer
{
    public $record;

    /**
     * Create a new job instance.
     *
     * @param $record
     */
    public function __construct(DescriptionScraper $scraper)
    {
        $this->scraper = $scraper ?: new DescriptionScraper();
    }

    /**
     * Process using php-marc and separate bibliographic and holdings.
     *
     * @param MarcRecord $biblio
     *
     * @return array
     */
    public function parseRecord(MarcRecord $rec)
    {
        // ---------------------------------------------

        $physicalItemMap = [
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

        $electronicPortfolioMap = [
            'a' => 'id',
            'f' => 'activation_date',
            'u' => 'linking_params',
            'v' => 'link_resolver_base_url',
            's' => 'status',
            'z' => 'collection',
            'y' => 'portfolio_id',
            'n' => 'public_note',
        ];

        $holdings = [];
        foreach ($rec->getFields('909') as $field) {
            $holding = [];

            foreach ($physicalItemMap as $c => $f) {
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
        foreach ($rec->getFields('910') as $field) {
            $holding = [];

            foreach ($electronicPortfolioMap as $c => $f) {
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

        // ---------------------------------------------------------------

        $biblio = $rec->jsonSerialize();
        $biblio['electronic'] = $this->isElectronic($rec);

        $biblio['cover_image'] = $this->getCoverImage($rec);
        $biblio['description'] = $this->getDescription($rec);

        return [$biblio, $holdings];
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
        $genre_ids = [];
        foreach ($biblio['subjects'] as $value) {
            if (!isset($value['vocabulary'])) {
                continue;
            }

            if (in_array($value['type'], ['648', '650', '651'])) {
                $subject = Subject::lookup($value['vocabulary'], $value['term'], $value['type']);
                if (is_null($subject)) {
                    $subject = Subject::create($value);
                }
                $subject_ids[] = $subject->id;

            } elseif ($value['type'] == '655') {
                $genre = Genre::lookup($value['vocabulary'], $value['term']);
                if (is_null($genre)) {
                    $genre = Genre::create($value);
                }
                $genre_ids[] = $genre->id;
            }
        }
        $doc->subjects()->sync($subject_ids);
        $doc->genres()->sync($genre_ids);

        // Extract cover from bibliographic record if no local cover exists
        if (isset($biblio['cover_image']) && is_null($doc->cover)) {
            try {
                $doc->storeCover($biblio['cover_image']);
            } catch (CannotFetchCover $exception) {
                \Log::error("Failed to fetch cover: {$biblio['cover_image']}. Error: {$exception->getMessage()}");
            }
        }

        return $doc;
    }

    /**
     * Add/update a single bibliographic record.
     *
     * @param BibliographicRecord $record
     * @return int|null
     */
    public function import(BibliographicRecord $record)
    {
        try {
            list($biblio, $holdings) = $this->parseRecord($record);
        } catch (ParserException $e) {
            $this->error('Failed to parse MARC record. Error "' . $e->getMessage() . '" in: ' . $e->getFile() . ':' . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());

            return null;
        }
        \Log::debug(json_encode($biblio));

        $doc = $this->importParsedRecord($biblio, $holdings);

        $doc->marc = $record->toXML('UTF-8', false, false);
        $doc->save();

        \Log::debug('[Marc21Importer] Imported ' . $doc->bibsys_id . ' as ' . $doc->id);

        if (!is_null($doc)) {
            Event::dispatch(new Marc21RecordImported($doc->id));
        }

        return $doc->id;
    }

    protected function isElectronic(BibliographicRecord $rec)
    {
        $f007 = $rec->getField('007');
        if (isset($f007)) {
            if (substr($f007->getData(), 0, 2) == 'cr') {
                return true;
            }
        }
        return false;
    }


    // <marc:datafield tag="956" ind1="4" ind2="2">
    //     <marc:subfield code="3">Omslagsbilde</marc:subfield>
    //     <marc:subfield code="u">http://innhold.bibsys.no/bilde/forside/?size=mini&amp;id=9780521176835.jpg</marc:subfield>
    //     <marc:subfield code="q">image/jpeg</marc:subfield>
    // </marc:datafield>
    protected function getCoverImage(BibliographicRecord $rec)
    {
        foreach ($rec->getFields('(856|956)', true) as $field) {
            $sf_3 = $field->getSubfield('3');
            $sf_u = $field->getSubfield('u');
            if ($sf_3 && $sf_u) {
                if (in_array($sf_3->getData(), ['Cover image', 'Omslagsbilde'])) {
                    return str_replace(
                        ['mini', 'LITE'],
                        ['stor', 'STOR'],
                        $sf_u->getData()
                    );
                }

            }
        }
    }

    // <marc:datafield tag="856" ind1="4" ind2="2">
    //     <marc:subfield code="3">Beskrivelse fra forlaget (kort)</marc:subfield>
    //     <marc:subfield code="u">http://content.bibsys.no/content/?type=descr_publ_brief&amp;isbn=0521176832</marc:subfield>
    // </marc:datafield>
    protected function getDescription(BibliographicRecord $rec)
    {
        foreach ($rec->getFields('(856|956)', true) as $field) {
            $sf_3 = $field->getSubfield('3');
            $sf_u = $field->getSubfield('u');
            if ($sf_3 && $sf_u) {
                if (preg_match('/beskrivelse fra forlaget/i', $sf_3->getData())) {
                    return $sf_u->getData();
                }
            }
        }
    }
}
