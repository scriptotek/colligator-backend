<?php

namespace Colligator;

class SearchEngine
{

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

        return \Es::search($payload);
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

        // Remove some stuff we don't need
        foreach (['agency', 'catalogingRules', 'debug', 'modified', 'is_series', 'is_multivolume', 'extent', 'cover_image'] as $key)
        {
            unset($body[$key]);
        }

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
        foreach ($doc->subjects as $subject) {
            $body['subjects'][$subject['vocabulary'] ?: 'keywords'][] = [
                'id' => array_get($subject, 'id'),
                'prefLabel' => array_get($subject, 'term'),
                'type' => array_get($subject, 'type'),
            ];
        }

        // Add holdings
        $body['holdings'] = array_values(array_filter($doc->holdings, function($holding) {
            return $holding['location'] == 'UBO' && $holding['sublocation'] == 'UREAL';
        }));

        // Add xisbns
        $body['xisbns'] = (new XisbnResponse($doc->xisbn))->getSimpleRepr();

        // TODO: Add description, etc.

        return $body;
    }

    /**
     * Add or update a document in the ElasticSearch index, making it searchable
     *
     * @param Document $doc
     */
    public function indexDocument(Document $doc)
    {
        // \Log::debug('Search engine: Indexing document ' . $doc->id);
        \Es::index([
            'index' => 'documents',
            'type' => 'document',
            'id' => $doc->id,
            'body' => $this->indexDocumentPayload($doc),
        ]);
    }

    public function createDocumentsIndex()
    {
        $indexParams = ['index' => 'documents'];
        $indexParams['body']['mappings']['document'] = [
            '_source' => [
                'enabled' => true
            ],
            'properties' => [
                'id' => ['type' => 'integer'],
                'created' => ['type' => 'date'],
                'modified' => ['type' => 'date'],
                'holdings' => [
                    'properties' => [
                        'created' => ['type' => 'date'],
                        'acquired' => ['type' => 'date'],
                    ]
                ]
            ]
        ];
        \Es::indices()->create($indexParams);
    }

    public function dropDocumentsIndex()
    {
        \Es::indices()->delete([
            'index' => 'documents'
        ]);
    }

}
