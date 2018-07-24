<?php

namespace Colligator\Search;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Exceptions\InvalidQueryException;
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
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->esIndex = env('ES_INDEX', 'documents');
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

        if ($request->has('sort')) {
            $payload['body']['sort'][$request->sort]['order'] = $request->get('order', 'asc');
        }

        try {
            $response = $this->client->search($payload);
        } catch (BadRequest400Exception $e) {
            $response = json_decode($e->getMessage(), true);
            $msg = array_get($response, 'error.root_cause.0.reason') ?: array_get($response, 'error');
            throw new InvalidQueryException($msg);
        }
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
     * Escape special characters
     * http://lucene.apache.org/core/old_versioned_docs/versions/2_9_1/queryparsersyntax.html#Escaping Special Characters.
     *
     * @param string $value
     *
     * @return string
     */
    public function sanitizeForQuery($value)
    {
        $chars = preg_quote('\\+-&|!(){}[]^~*?:');
        $value = preg_replace('/([' . $chars . '])/', '\\\\\1', $value);

        return $value;
        //
        // # AND, OR and NOT are used by lucene as logical operators. We need
        // # to escape them
        // ['AND', 'OR', 'NOT'].each do |word|
        //   escaped_word = word.split('').map {|char| "\\#{char}" }.join('')
        //   str = str.gsub(/\s*\b(#{word.upcase})\b\s*/, " #{escaped_word} ")
        // end

        // # Escape odd quotes
        // quote_count = str.count '"'
        // str = str.gsub(/(.*)"(.*)/, '\1\"\3') if quote_count % 2 == 1
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
            // Allow raw queries
            $query[] = $request->q;
        }
        if ($request->has('collection')) {
            $col = Collection::findOrFail($request->collection);
            $query[] = 'collections:"' . $this->sanitizeForQuery($col->name) . '"';
        }
        if ($request->has('subject')) {
            $query[] = '(subjects.noubomn.prefLabel:"' . $this->sanitizeForQuery($request->subject) . '"' .
                    ' OR subjects.bare.prefLabel:"' . $this->sanitizeForQuery($request->subject) . '"' .
                    ' OR genres.noubomn.prefLabel:"' . $this->sanitizeForQuery($request->subject) . '")';
                // TODO: Vi bør vel antakelig skille mellom X som emne og X som form/sjanger ?
                //       Men da må frontend si fra hva den ønsker, noe den ikke gjør enda.
        }
        if ($request->has('language')) {
            $query[] = 'language:"' . $this->sanitizeForQuery($request->language) . '"' ;
        }
        if ($request->has('genre')) {
            $query[] = 'genres.noubomn.prefLabel:"' . $this->sanitizeForQuery($request->genre) . '"';
        }
        if ($request->has('real')) {
            dd('`real` is (very) deprecated, please use `subject` instead.');
        }
        $query = count($query) ? implode(' AND ', $query) : '';

        return $query;
    }

    public function basePayload()
    {
        return [
            'index' => $this->esIndex,
            'type'  => $this->esType,
        ];
    }

    public function getFullType($type)
    {
        $typemap = ['subject' => 'Colligator\\Subject', 'genre' => 'Colligator\\Genre'];
        if (!isset($typemap[$type])) {
            throw new \InvalidArgumentException();
        }

        return $typemap[$type];
    }

    /**
     * Returns the number of documents the subject is used on.
     *
     * @param int $id
     *
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
     *
     * @return array
     */
    public function addToUsageCache($entity_ids, $type)
    {
        $fullType = $this->getFullType($type);
        if (!is_array($entity_ids)) {
            $entity_ids = [$entity_ids];
        }
        $res = \DB::table('entities')
            ->select(['entity_id', \DB::raw('count(document_id) as doc_count')])
            ->whereIn('entity_id', $entity_ids)
            ->where('entity_type', $fullType)
            ->groupBy('entity_id')
            ->get();

        foreach ($entity_ids as $sid) {
            array_set($this->usage, $type . '.' . $sid, 0);
        }

        foreach ($res as $row) {
            array_set($this->usage, $type . '.' . $row->entity_id, intval($row->doc_count));
        }
    }

    public function buildCompleteUsageCache()
    {
        $typemap = ['Colligator\\Subject' => 'subject', 'Colligator\\Genre' => 'genre'];
        $query = \DB::table('entities')
                    ->select(['entity_id', 'entity_type', \DB::raw('count(document_id) as doc_count')])
                    ->groupBy('entity_id', 'entity_type');
        $query->orderBy('entity_id')->orderBy('entity_type')->chunk(5000, function ($rows) use ($typemap) {
            foreach ($rows as $row) {
                $type = $typemap[$row->entity_type];
                array_set($this->usage, $type . '.' . $row->entity_id, intval($row->doc_count));
            }
        });
    }

    /**
     * Add or update a document in the ElasticSearch index, making it searchable.
     *
     * @param Document $doc
     * @param int      $indexVersion
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
            throw new \ErrorException('ElasticSearch failed to index the document ' . $doc->id . '. Please see the log for payload and full error response. Error message: ' . $e->getMessage());
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

    public function createVersion($version = null)
    {
        if (is_null($version)) {
            $version = $this->getCurrentVersion() + 1;
        }
        $indexParams = ['index' => $this->esIndex . '_v' . $version];
        $indexParams['body']['settings']['analysis']['char_filter']['isbn_filter'] = [
            'type'        => 'pattern_replace',
            'pattern'     => '-',
            'replacement' => '',
        ];
        $indexParams['body']['settings']['analysis']['analyzer']['isbn_analyzer'] = [
            'type'        => 'custom',
            'char_filter' => ['isbn_filter'],
            'tokenizer'   => 'keyword',
            'filter'      => ['lowercase'],
        ];
        $indexParams['body']['mappings']['document'] = [
            '_source' => [
                'enabled' => true,
            ],
            'properties' => [
                'id'        => ['type' => 'integer'],
                'created'   => ['type' => 'date'],
                'modified'  => ['type' => 'date'],
                'bibsys_id' => ['type' => 'string', 'index' => 'not_analyzed'],
                'isbns'     => [
                    'type'     => 'string',
                    'analyzer' => 'isbn_analyzer',
                ],
                'holdings' => [
                    'properties' => [
                        'created'  => ['type' => 'date'],
                        'acquired' => ['type' => 'date'],
                    ],
                ],
                'cover' => [
                    'properties' => [
                        'created'  => ['type' => 'date'],
                        'modified' => ['type' => 'date'],
                    ],
                ],
            ],
        ];
        $this->client->indices()->create($indexParams);

        return $version;
    }

    public function dropVersion($version)
    {
        try {
            $this->client->indices()->delete([
                'index' => $this->esIndex . '_v' . $version,
            ]);
        } catch (Missing404Exception $e) {
            # Didn't exist in the beginning, that's ok.
        }
    }

    public function addAction(&$actions, $action, $version)
    {
        if ($version) {
            $actions[] = [$action => ['index' => $this->esIndex . '_v' . $version, 'alias' => $this->esIndex]];
        }
    }

    public function activateVersion($newVersion)
    {
        $oldVersion = $this->getCurrentVersion();
        $actions = [];
        $this->addAction($actions, 'remove', $oldVersion);
        $this->addAction($actions, 'add', $newVersion);
        if (count($actions)) {
            $this->client->indices()->updateAliases(['body' => ['actions' => $actions]]);
        }
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
