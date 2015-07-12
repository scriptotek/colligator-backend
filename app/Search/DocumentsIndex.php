<?php

namespace Colligator\Search;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Http\Requests\SearchDocumentsRequest;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class DocumentsIndex
{

    public $esIndex = 'documents';
    public $esType = 'document';

    /**
     * @var Client
     */
    public $client;

    /**
     * @var array
     */
    public $usage = [];

    /**
     * @param Client $client
     */
    function __construct(Client $client) {
        $this->client = $client;
    }

    /**
     * Search for documents in ElasticSearch.
     *
     * @param SearchDocumentsRequest $request
     *
     * @return array
     */
    public function search(SearchDocumentsRequest $request)
    {
        $payload = $this->basePayload();
        $payload['from'] = $request->offset ?: 0;
        $payload['size'] = $request->limit ?: 25;

        $query = $this->queryStringFromRequest($request);
        if (!empty($query)) {
            $payload['body']['query']['query_string']['query'] = $query;
        }

        $response = $this->client->search($payload);
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
    public function get($id)
    {
        $payload = $this->basePayload();
        $payload['id'] = $id;

        try {
            $response = $this->client->get($payload);
        } catch (Missing404Exception $e) {
            return;
        }

        return $response['_source'];
    }

    /**
     * Builds a query string query from a SearchDocumentsRequest.
     *
     * @param SearchDocumentsRequest $request
     *
     * @return string
     */
    public function queryStringFromRequest(SearchDocumentsRequest $request)
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

    public function basePayload()
    {
        return [
            'index' => $this->esIndex,
            'type' => $this->esType,
        ];
    }

    public function getFullType($type)
    {
        $typemap = ['subject' => 'Colligator\\Subject', 'genre' => 'Colligator\\Genre'];
        if (!isset($typemap[$type])) {
            throw new \InvalidArgumentException;
        }
        return $typemap[$type];
    }

    /**
     * Returns the number of documents the subject is used on
     *
     * @param int $id
     * @return int
     */
    public function getUsageCount($id, $type)
    {
        $this->getFullType($type);
        $arg = $type . '.' . $id;
        if (is_null(array_get($this->usage, $arg))) {
            $this->addToUsageCache($id, $type);
        }
        return array_get($this->usage, $arg);
    }

    /**
     * Build an array of document usage count per subject.
     *
     * @param array|int $subject_ids
     * @return array
     */
    public function addToUsageCache($entity_ids, $type)
    {
        $fullType = $this->getFullType($type);
        if (!is_array($entity_ids)) {
            $entity_ids = [$entity_ids];
        }
        $res = \DB::table('authorities')
            ->select(['authority_id', \DB::raw('count(document_id) as doc_count')])
            ->whereIn('authority_id', $entity_ids)
            ->where('authority_type', $fullType)
            ->groupBy('authority_id')
            ->get();

        foreach ($entity_ids as $sid) {
            array_set($this->usage, $type . '.' . $sid, 0);
        }

        foreach ($res as $row) {
            array_set($this->usage, $type . '.' . $row->authority_id, intval($row->doc_count));
        }
    }

    /**
     * Add or update a document in the ElasticSearch index, making it searchable.
     *
     * @param Document $doc
     * @param int $indexVersion
     *
     * @throws \ErrorException
     */
    public function index(Document $doc, $indexVersion = null)
    {
        $payload = $this->basePayload();
        if (!is_null($indexVersion)) {
            $payload['index'] = $this->esIndex . '_v' . $indexVersion;
        }
        $payload['id'] = $doc->id;

        $sdoc = new SearchableDocument($doc, $this);
        $payload['body'] = $sdoc->toArray();

        try {
            $this->client->index($payload);
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
    public function indexById($docId)
    {
        $this->index(Document::with('subjects', 'cover')->findOrFail($docId));
    }

    public function createVersion($version=null)
    {
        if (is_null($version)) {
            $version = $this->getCurrentVersion() + 1;
        }
        $indexParams = ['index' => $this->esIndex . '_v' . $version];
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
        $this->client->indices()->create($indexParams);
        return $version;
    }

    public function dropVersion($version = 1)
    {
        $this->client->indices()->delete([
            'index' => $this->esIndex . '_v' . $version,
        ]);
    }

    public function activateVersion($newVersion)
    {
        $oldVersion = $this->getCurrentVersion();
        $actions = [];
        if ($oldVersion) {
            $actions[] = ['remove' => ['index' => $this->esIndex . '_v' . $oldVersion, 'alias' => $this->esIndex]];
        }
        $actions[] = ['add' => ['index' => $this->esIndex . '_v' . $newVersion, 'alias' => $this->esIndex]];
        $this->client->indices()->updateAliases(['body' => ['actions' => $actions]]);
    }

    public function versionExists($version)
    {
        return $this->client->indices()->exists(['index' => $this->esIndex . '_v' . $version]);
    }

    public function getCurrentVersion()
    {
        $currentIndex = null;
        foreach ($this->client->indices()->getAliases() as $index => $data) {
            if (in_array($this->esIndex, array_keys($data['aliases']))) {
                $currentIndex = $index;
            }
        }
        return is_null($currentIndex) ? 0 : intval(explode('_v', $currentIndex)[1]);
    }

}
