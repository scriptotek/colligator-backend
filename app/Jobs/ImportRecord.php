<?php

namespace Colligator\Jobs;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Marc21Importer;
use Colligator\Search\DocumentsIndex;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;

class ImportRecord extends Job
{
    protected $record;
    protected $collection;

    /**
     * ImportRecord constructor.
     * @param Collection $collection
     * @param QuiteSimpleXMLElement $record
     */
    public function __construct(Collection $collection, QuiteSimpleXMLElement $record)
    {
        $this->collection = $collection;
        $this->record = $record;
    }

    /**
     * @param Marc21Importer $importer
     */
    public function handle(DocumentsIndex $docIndex, Marc21Importer $importer)
    {
        $docId = $importer->import($this->record);
        $this->imported($docIndex, $docId);
    }

    /**
     * @param DocumentsIndex $docIndex
     * @param int $docId
     */
    public function imported($docIndex, $docId)
    {
        $doc = Document::with('subjects', 'genres', 'cover')->find($docId);

        if (!$this->collection->documents->contains($doc->id)) {
            $this->collection->documents()->attach($doc->id);
        }

        // Add/update ElasticSearch
        $docIndex->index($doc);
    }
}