<?php

namespace Colligator;

use Elasticsearch\Client as EsClient;

class SearchEngine
{

    public function __construct(EsClient $client)
    {
        $this->client = $client;
    }

    /**
     * Search for documents in ElasticSearch
     *
     * @param string $query
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function searchDocuments($query = null, $offset = 0, $limit = 25)
    {
        $payload = [
             'index' => 'documents',
             'type' => 'document',
             'body' => [],
             'from' => $offset,
             'size' => $limit,
         ];

        if (!is_null($query)) {
            $payload['body']['query']['query_string']['query'] = $query;
        }

        return $this->client->search($payload);
    }

    /**
     * Generate payload for indexing a document in ElasticSearch
     *
     * @param Document $doc
     * @return array
     */
    public function indexDocumentPayload(Document $doc)
    {
        $body = $doc->bibliographic;  // PHP makes a copy for us, right?

        $body['id'] = $doc->id;
        $body['bibsys_id'] = $doc->bibsys_id;

        // Add local collections
        $body['collections'] = [];
        foreach ($doc->collections as $collection) {
            $body['collections'][] = $collection['name'];
        }

        // Add covers
        $body['covers'] = [];
        foreach ($doc->covers as $cover) {
            $body['covers'][] = $cover->toArray();
        }

        // Add subjects
        $body['subjects'] = [];
        foreach ($doc->bibliographic['subjects'] as $subject) {
            $body['subjects'][$subject['vocabulary'] ?: 'keywords'][] = [
                'id' => array_get($subject, 'id'),
                'prefLabel' => array_get($subject, 'term'),
                'type' => array_get($subject, 'type'),
            ];
        }

        // Add holdings
        $body['holdings'] = $doc->holdings;

        // Plural to singular, oh my!
        // $body['isbn'] = array_get($body, 'isbns', []);
        // unset($body['isbns']);
        // $body['creator'] = array_get($body, 'creators', []);
        // unset($body['creators']);

        // TODO: Add covers, description, etc.

        return $body;
    }

    /**
     * Add or update a document in the ElasticSearch index, making it searchable
     *
     * @param Document $doc
     */
    public function indexDocument(Document $doc)
    {
        \Log::info('Search engine: Indexing document ' . $doc->id);
        $this->client->index([
            'index' => 'documents',
            'type' => 'document',
            'id' => $doc->id,
            'body' => $this->indexDocumentPayload($doc),
        ]);
    }

    public function createDocumentsIndex()
    {
        $this->client->indices()->create([
            'index' => 'documents'
        ]);

        // TODO : Add some mappings
    }

    public function dropDocumentsIndex()
    {
        $this->client->indices()->delete([
            'index' => 'documents'
        ]);
    }

}
