<?php

namespace Colligator\Search;

use Colligator\Document;
use Colligator\Entity;
use Colligator\XisbnResponse;

class SearchableDocument
{
    /**
     * @var DocumentsIndex
     */
    protected $docIndex;

    /**
     * @var string
     */
    protected $sortableCallCodePattern = '/FA ([0-9]+)(\/([A-Z]))?/';

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

        // Add entities
        $entityKeys = [
            Entity::SUBJECT => 'subjects',
            Entity::GENRE => 'genres',
            Entity::LOCAL_SUBJECT => 'local_subjects',
            Entity::LOCAL_GENRE => 'local_genres',
            Entity::CREATOR => 'creators',
        ];
        foreach ($entityKeys as $key => $val) {
            $body[$val] = [];
        }
        foreach ($this->doc->entities as $entity) {
            if ($entity->type == Entity::CREATOR) {
                $body[$entityKeys[$entity->type]][] = [
                    'id' => $entity->id,
                    'prefLabel' => str_replace('--', ' : ', $entity->term),
                    'type' => $entity->type,
                    'relationship' => $entity->pivot->relationship,
                    'count' => $this->docIndex->getUsageCount($entity->id),
                ];
            } else {
                $body[$entityKeys[$entity->type]][$entity['vocabulary'] ?: 'keywords'][] = [
                    'id' => $entity->id,
                    'prefLabel' => str_replace('--', ' : ', $entity->term),
                    'type' => $entity->type,
                    'count' => $this->docIndex->getUsageCount($entity->id),
                ];
            }
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

            // @TODO: https://github.com/scriptotek/colligator-backend/issues/34
            // Not sure how to handle this in Alma yet
            unset($body['other_form']);
            // $otherFormDoc = Document::where('bibsys_id', '=', $otherFormId)->firstOrFail();
            // $body['other_form'] = [
            //     'id'         => $otherFormDoc->id,
            //     'bibsys_id'  => $otherFormDoc->bibsys_id,
            //     'electronic' => $otherFormDoc->isElectronic(),
            // ];
            // $this->addHoldings($body['other_form'], $otherFormDoc);
        }

        // Add 'cannot_find_cover'
        $body['cannot_find_cover'] = $this->doc->cannot_find_cover;

        return $body;
    }

    public function sortableCallCode($holding)
    {
        $m = preg_match($this->sortableCallCodePattern, array_get($holding, 'callcode'), $matches);
        if ($m) {
            return intval($matches[1]);
            // TODO: Også ta hensyn til undersortering i $matches[3], men
            // denne er en blanding av romertall og alfabetisk sortering
            // https://github.com/scriptotek/colligator-backend/issues/28
        }

        return;
    }

    public function addHoldings(&$body, Document $doc)
    {
        if ($doc->isElectronic()) {

            // @ TODO: Virker ikke med Alma
            // https://github.com/scriptotek/colligator-backend/issues/34
            // $body['fulltext'] = $this->fulltextFromHoldings($doc->holdings);

        } else {
            $body['holdings'] = [];
            foreach ($doc->holdings as $holding) {
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
