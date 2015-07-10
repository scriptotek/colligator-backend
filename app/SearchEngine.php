<?php

namespace Colligator;

use Colligator\Http\Requests\SearchDocumentsRequest;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class SearchEngine
{

    /**
     * Builds a query string query from a SearchDocumentsRequest.
     *
     * @param SearchDocumentsRequest $request
     *
     * @return string
     */
    public function queryFromRequest(SearchDocumentsRequest $request)
    {
        $query = [];
        if ($request->has('q')) {
            $query[] = $request->q;
        }
        if ($request->has('collection')) {
            $col = Collection::find($request->collection);
            $query[] = 'collections:' . $col->name;
        }
        if ($request->has('real')) {
            $query[] = 'subjects.noubomn.prefLabel:' . $request->real;
        }
        $query = count($query) ? implode(' AND ', $query) : '';

        return $query;
    }

    /**
     * Search for documents in ElasticSearch.
     *
     * @param SearchDocumentsRequest $request
     *
     * @return array
     */
    public function searchDocuments(SearchDocumentsRequest $request)
    {
        $query = $this->queryFromRequest($request);
        $payload = [
             'index' => 'documents',
             'type' => 'document',
             'body' => [],
             'from' => $request->offset ?: 0,
             'size' => $request->limit ?: 25,
         ];

        if (!empty($query)) {
            $payload['body']['query']['query_string']['query'] = $query;
        }

        $response = \Es::search($payload);
        $response['offset'] = $payload['from'];
        return $response;
    }

    /**
     * Return a single document identified by ID.
     *
     * @param int $id
     *
     * @return array
     */
    public function getDocument($id)
    {
        $payload = [
            'index' => 'documents',
            'type' => 'document',
            'id' => $id,
        ];
        try {
            $response = \Es::get($payload);
        } catch (Missing404Exception $e) {
            return;
        }

        return $response['_source'];
    }

    /**
     * Generate payload for indexing a document in ElasticSearch.
     *
     * @param Document $doc
     *
     * @return array
     */
    public function indexDocumentPayload(Document $doc)
    {
        $body = $doc->bibliographic;  // PHP makes a copy for us, right?

        $body['id'] = $doc->id;
        $body['bibsys_id'] = $doc->bibsys_id;

        // Remove some stuff we don't need
        foreach (['agency', 'catalogingRules', 'debug', 'modified', 'extent', 'cover_image'] as $key) {
            unset($body[$key]);
        }

        // Add local collections
        $body['collections'] = [];
        foreach ($doc->collections as $collection) {
            $body['collections'][] = $collection['name'];
        }

        // Add cover
        $body['cover'] = $doc->cover ? $doc->cover->toArray() : null;

        // Add subjects
        $body['subjects'] = [];
        foreach ($doc->subjects as $subject) {
            $body['subjects'][$subject['vocabulary'] ?: 'keywords'][] = [
                'id' => array_get($subject, 'id'),
                'prefLabel' => array_get($subject, 'term'),
                'type' => array_get($subject, 'type'),
            ];
        }

        // Add genres
        $body['genres'] = [];
        foreach ($doc->genres as $genre) {
            $body['genres'][$genre['vocabulary'] ?: 'keywords'][] = [
                'id' => array_get($genre, 'id'),
                'prefLabel' => array_get($genre, 'term'),
            ];
        }

        // Add holdings
        $this->addHoldings($body, $doc);

        // Add xisbns
        $body['xisbns'] = (new XisbnResponse($doc->xisbn))->getSimpleRepr();

        // Add description
        $body['description'] = $doc->description;

        // Add 'other form'
        $otherFormId = array_get($body, 'other_form.id');
        if (!empty($otherFormId)) {
            $otherFormDoc = Document::where('bibsys_id', '=', $otherFormId)->firstOrFail();
            $body['other_form'] = [
                'id' => $otherFormDoc->id,
                'bibsys_id' => $otherFormDoc->bibsys_id,
                'electronic' => $otherFormDoc->isElectronic(),
            ];
            $this->addHoldings($body['other_form'], $otherFormDoc);
        }

        return $body;
    }

    public function addHoldings(&$body, Document $doc)
    {
        if ($doc->isElectronic()) {
            $body['fulltext'] = $this->fulltextFromHoldings($doc->holdings);
        } else {
            $holdings = [];
            foreach ($doc->holdings as $holding) {
                if ($holding['location'] == 'UBO' && $holding['sublocation'] == 'UREAL') {
                    array_forget($holding, 'fulltext');
                    array_forget($holding, 'bibliographic_record');
                    array_forget($holding, 'nonpublic_notes');
                    $holdings[] = $holding;
                }
            }
            $body['holdings'] = $holdings;
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

    /**
     * Add or update a document in the ElasticSearch index, making it searchable.
     *
     * @param Document $doc
     *
     * @throws \ErrorException
     */
    public function indexDocument(Document $doc)
    {
        // \Log::debug('Search engine: Indexing document ' . $doc->id);
        $payload = $this->indexDocumentPayload($doc);
        try {
            \Es::index([
                'index' => 'documents',
                'type' => 'document',
                'id' => $doc->id,
                'body' => $payload,
            ]);
        } catch (BadRequest400Exception $e) {
            \Log::error('ElasticSearch returned error: ' . $e->getMessage() . '. Our request: ' . var_export($payload, true));
            throw new \ErrorException($e);
        }
    }

    /**
     * Add or update a document in the ElasticSearch index, making it searchable.
     *
     * @param int $docId
     *
     * @throws \ErrorException
     */
    public function indexDocumentById($docId)
    {
        $this->indexDocument(Document::with('subjects', 'cover')->findOrFail($docId));
    }

    public function createDocumentsIndex()
    {
        $indexParams = ['index' => 'documents'];
        $indexParams['body']['settings']['analysis']['char_filter']['isbn_filter'] = [
            'type' => 'pattern_replace',
            'pattern' => '-',
            'replacement' => '',
        ];
        $indexParams['body']['settings']['analysis']['analyzer']['isbn_analyzer'] = [
            'type' => 'custom',
            'char_filter' => ['isbn_filter'],
            'tokenizer' => 'keyword',
            'filter' => ['lowercase'],
        ];
        $indexParams['body']['mappings']['document'] = [
            '_source' => [
                'enabled' => true,
            ],
            'properties' => [
                'id' => ['type' => 'integer'],
                'created' => ['type' => 'date'],
                'modified' => ['type' => 'date'],
                'bibsys_id' => ['type' => 'string', 'index' => 'not_analyzed'],
                'isbns' => [
                    'type' => 'string',
                    'analyzer' => 'isbn_analyzer'
                ],
                'holdings' => [
                    'properties' => [
                        'created' => ['type' => 'date'],
                        'acquired' => ['type' => 'date'],
                    ],
                ],
            ],
        ];
        \Es::indices()->create($indexParams);
    }

    public function dropDocumentsIndex()
    {
        \Es::indices()->delete([
            'index' => 'documents',
        ]);
    }
}
