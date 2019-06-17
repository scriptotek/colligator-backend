<?php

namespace Colligator\Jobs;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Marc21Importer;
use Colligator\Search\DocumentsIndex;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Scriptotek\Marc\Record;

class ImportRecord extends Job
{
    protected $identifier;
    protected $marc;
    protected $collection;

    /**
     * ImportRecord constructor.
     * @param Collection $collection
     * @param QuiteSimpleXMLElement $oaiRecord
     */
    public function __construct(Collection $collection, QuiteSimpleXMLElement $oaiRecord)
    {
        $this->collection = $collection;

        $this->identifier = $oaiRecord->text('oai:header/oai:identifier');
        $this->marc = $oaiRecord->first('oai:metadata/marc:record')->asXML();
    }

    /**
     * @param Marc21Importer $importer
     */
    public function handle(DocumentsIndex $docIndex, Marc21Importer $importer)
    {
        $record = Record::fromString($this->marc);

        \Log::info('Importing MARC record ' . $this->identifier);
        // \Log::debug($this->marc);

        $docId = $importer->import($record);
        $this->imported($docIndex, $docId);
    }

    /**
     * @param DocumentsIndex $docIndex
     * @param int $docId
     */
    public function imported($docIndex, $docId)
    {
        $doc = Document::with('subjects', 'genres', 'cover')->find($docId);
        $doc->oai_id = $this->identifier;
        $doc->save();

        if (!$this->collection->documents->contains($doc->id)) {
            $this->collection->documents()->attach($doc->id);
        }

        // Add/update ElasticSearch
        $docIndex->index($doc);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        return ['import-record', 'collection:' . $this->collection->name];
    }
}
