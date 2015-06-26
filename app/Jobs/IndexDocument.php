<?php

namespace Colligator\Jobs;

use Colligator\Document;
use Illuminate\Contracts\Bus\SelfHandling;
use Elasticsearch\Client as EsClient;

class IndexDocument extends Job implements SelfHandling
{

    /**
     * The document id.
     *
     * @var int
     */
    public $doc_id;

    /**
     * Create a new job instance.
     *
     * @param Document $doc
     */
    public function __construct(Document $doc)
    {
        $this->doc = $doc;
    }

    /**
     * Generate a payload to index a document in ElasticSearch
     *
     */
    public function generateElasticSearchPayload()
    {
        $doc = $this->doc;

        $body = $doc->bibliographic;  // PHP makes a copy for us

        $body['bibsys_id'] = $doc->bibsys_id;
        $body['holdings'] = $doc->holdings;

        // Add top-level field for Realfagstermer
        $body['real'] = [];
        foreach ($doc->subjects as $subject) {
            if (array_get($subject, 'vocabulary') == 'noubomn') {
                $body['real'][] = $subject['term'];
            }
        }

        // Add local collections
        $body['collection'] = [];
        foreach ($doc->collections as $collection) {
            $body['collection'][] = $collection['name'];
        }

        // Plural to singular, oh my!
        $body['isbn'] = array_get($body, 'isbns', []);
        unset($body['isbns']);
        $body['creator'] = array_get($body, 'creators', []);
        unset($body['creators']);

        // TODO: Add covers, description, etc.

        return $body;
    }

    /**
     * Execute the job.
     *
     * @param EsClient $es
     */
    public function handle(EsClient $es)
    {

        $es->index([
            'index' => 'documents',
            'type' => 'document',
            'id' => $this->doc->id,
            'body' => $this->generateElasticSearchPayload(),
        ]);

        /*
         * array(5) {
          ["_index"]=>
          string(9) "documents"
          ["_type"]=>
          string(8) "document"
          ["_id"]=>
          string(2) "28"
          ["_version"]=>
          int(2)
          ["created"]=>
          bool(false)
        }
        */
    }
}
