<?php

namespace Colligator\Jobs;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Marc21Importer;
use Colligator\Search\DocumentsIndex;
use Scriptotek\Marc\Record;

class ImportRecords extends Job
{
    protected $collection;
    protected $records;

    /**
     * ImportRecords constructor.
     * @param Collection $collection
     * @param array $records
     */
    public function __construct(Collection $collection, $records)
    {
        $this->collection = $collection;
        $this->records = $records;
    }

    /**
     * @param DocumentsIndex $docIndex
     * @param Marc21Importer $importer
     */
    public function handle(DocumentsIndex $docIndex, Marc21Importer $importer)
    {
        //        \DB::listen(function ($query) {
        //            print("$query->time : $query->sql\n\n");
        //            // $query->sql
        //            // $query->bindings
        //            // $query->time
        //        });

        \Log::info('Importing ' . count($this->records) . ' records');
        foreach ($this->records as $rec) {
            try {
                \Log::info('Importing MARC record ' . $rec['oai_id']);
                $marc = Record::fromString($rec['marc']);
                $doc = $this->importRecord($importer, $marc, $rec['oai_id']);
                $docIndex->index($doc);
            } catch (\Error $exception) {
                \Log::error('Failed to import MARC record ' . $rec['oai_id']);
            }
        }
    }

    /**
     * @param Marc21Importer $importer
     * @param Record $marc
     * @param string $oaiId
     * @return Document
     */
    public function importRecord(Marc21Importer $importer, Record $marc, string $oaiId)
    {
        $docId = $importer->import($marc);

        // Load subjects, genres, cover because we will use that when indexing in ES later
        $doc = Document::with('subjects', 'genres', 'cover')->find($docId);
        $doc->oai_id = $oaiId;
        $doc->save();

        // Optimized query, since the default Eloquent query includes joins here
        if ( ! \DB::table('collection_document')
                ->where('collection_id', $this->collection->id)
                ->where('document_id', $doc->id)
                ->first()
        ) {
            $this->collection->documents()->attach($doc->id);
        }

        return $doc;
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
