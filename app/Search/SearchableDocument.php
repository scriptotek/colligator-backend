<?php

namespace Colligator\Search;

use Colligator\Document;
use Colligator\XisbnResponse;

class SearchableDocument
{
    /**
     * @var DocumentsIndex
     */
    protected $docIndex;

    /**
     * @param Document       $doc
     * @param DocumentsIndex $docIndex
     */
    public function __construct(Document $doc, DocumentsIndex $docIndex)
    {
        $this->doc = $doc;
        $this->docIndex = $docIndex;
    }

    /**
     * Generate ElasticSearch index payload.
     *
     * @return array
     */
    public function toArray()
    {
        $body = $this->doc->bibliographic;  // PHP makes a copy for us

        $body['id'] = $this->doc->id;
        $body['bibsys_id'] = $this->doc->bibsys_id;

        // Remove some stuff we don't need
        foreach (['agency', 'catalogingRules', 'debug', 'modified', 'extent', 'cover_image'] as $key) {
            unset($body[$key]);
        }

        // Add local collections
        $body['collections'] = [];
        foreach ($this->doc->collections as $collection) {
            $body['collections'][] = $collection['name'];
        }

        // Add cover
        $body['cover'] = $this->doc->cover ? $this->doc->cover->toArray() : null;

        // Add subjects
        $body['subjects'] = [];
        foreach ($this->doc->subjects as $subject) {
            $body['subjects'][$subject['vocabulary'] ?: 'keywords'][] = [
                'id'        => array_get($subject, 'id'),
                'prefLabel' => str_replace('--', ' : ', array_get($subject, 'term')),
                'type'      => array_get($subject, 'type'),
                'count'     => $this->docIndex->getUsageCount(array_get($subject, 'id'), 'subject'),
            ];
        }

        // Add genres
        $body['genres'] = [];
        foreach ($this->doc->genres as $genre) {
            $body['genres'][$genre['vocabulary'] ?: 'keywords'][] = [
                'id'        => array_get($genre, 'id'),
                'prefLabel' => array_get($genre, 'term'),
                'count'     => $this->docIndex->getUsageCount(array_get($genre, 'id'), 'genre'),
            ];
        }

        // Add holdings
        $this->addHoldings($body, $this->doc);

        // Add xisbns
        $body['xisbns'] = (new XisbnResponse($this->doc->xisbn))->getSimpleRepr();

        // Add description
        $body['description'] = $this->doc->description;

        // Add 'other form'
        $otherFormId = array_get($body, 'other_form.id');
        if (!empty($otherFormId)) {
            $otherFormDoc = Document::where('bibsys_id', '=', $otherFormId)->firstOrFail();
            $body['other_form'] = [
                'id'         => $otherFormDoc->id,
                'bibsys_id'  => $otherFormDoc->bibsys_id,
                'electronic' => $otherFormDoc->isElectronic(),
            ];
            $this->addHoldings($body['other_form'], $otherFormDoc);
        }

        return $body;
    }

    public function sortableCallCode($holding)
    {
        if ($holding['shelvinglocation'] == 'UREAL Samling 42') {
            $m = preg_match('/FA ([0-9]+)(\/([A-Z]))?/', $holding['callcode'], $matches);
            if ($m) {
                return intval($matches[1]);
                // TODO: Også ta hensyn til undersortering i $matches[3], men
                // denne er en blanding av romertall og alfabetisk sortering
                // https://github.com/scriptotek/colligator-backend/issues/28
            }
        }

        return;
    }

    public function addHoldings(&$body, Document $doc)
    {
        if ($doc->isElectronic()) {
            $body['fulltext'] = $this->fulltextFromHoldings($doc->holdings);
        } else {
            $body['holdings'] = [];
            foreach ($doc->holdings as $holding) {
                if ($holding['location'] == 'UBO' && $holding['sublocation'] == 'UREAL') {
                    array_forget($holding, 'fulltext');
                    array_forget($holding, 'bibliographic_record');
                    array_forget($holding, 'nonpublic_notes');
                    $s = $this->sortableCallCode($holding);
                    if (!is_null($s)) {
                        $holding['callcodeSortable'] = $s;
                    }
                    $body['holdings'][] = $holding;
                }
            }
        }
    }

    public function fulltextFromHoldings($holdings)
    {
        $fulltext = [
            'access' => false,
        ];
        foreach ($holdings as $holding) {
            if (!count($holding['fulltext'])) {
                continue;
            }
            if ($holding['location'] == 'UBO' || stripos($holding['fulltext'][0]['comment'], 'gratis') !== false) {
                $fulltext = $holding['fulltext'][0];
                $fulltext['access'] = true;
            }
        }

        return $fulltext;
    }
}
