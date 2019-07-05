<?php

namespace Colligator\Search;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Exceptions\InvalidQueryException;
use Colligator\Http\Requests\SearchDocumentsRequest;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;

class DocumentsIndex extends ElasticSearchIndex
{
    public $model = Document::class;
    public $modelRelationships = ['entities', 'cover'];
    public $name = 'documents';
    public $documentType = 'document';

    public $usage = [];

    public $settings = [
        'analysis' => [
            'char_filter' => [
                'isbn_filter' => [
                    'type'        => 'pattern_replace',
                    'pattern'     => '-',
                    'replacement' => '',
                ],
            ],
            'analyzer' => [
                'isbn_analyzer' => [
                    'type'        => 'custom',
                    'char_filter' => ['isbn_filter'],
                    'tokenizer'   => 'keyword',
                    'filter'      => ['lowercase'],
                ],
            ],
        ],
    ];

    public $mappings = [
        '_source' => [
            'enabled' => true,
        ],
        'properties' => [
            'id'        => ['type' => 'integer'],
            'created'   => ['type' => 'date'],
            'modified'  => ['type' => 'date'],
            'bibsys_id' => ['type' => 'keyword'],
            'isbns'     => [
                'type'     => 'text',
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

    /**
     * Returns the number of documents the subject is used on.
     *
     * @param int $id
     *
     * @return int
     */
    public function getUsageCount($id)
    {
        if (is_null(array_get($this->usage, $id))) {
            $this->addToUsageCache($id);
        }

        return array_get($this->usage, $id);
    }

    /**
     * Build an array of document usage count per subject.
     *
     * @param array|int $entity_ids
     *
     * @return array
     */
    public function addToUsageCache($entity_ids)
    {
        if (!is_array($entity_ids)) {
            $entity_ids = [$entity_ids];
        }
        $res = \DB::table('document_entity')
            ->select(['entity_id', \DB::raw('count(document_id) as doc_count')])
            ->whereIn('entity_id', $entity_ids)
            ->groupBy('entity_id')
            ->get();

        foreach ($entity_ids as $entity_id) {
            array_set($this->usage, $entity_id, 0);
        }

        foreach ($res as $row) {
            array_set($this->usage, $row->entity_id, intval($row->doc_count));
        }
    }

    public function prepareReindex()
    {
        $query = \DB::table('document_entity')
                    ->select(['entity_id', \DB::raw('count(document_id) as doc_count')])
                    ->groupBy('entity_id');
        $query->orderBy('entity_id')->chunk(5000, function ($rows) {
            foreach ($rows as $row) {
                array_set($this->usage, $row->entity_id, intval($row->doc_count));
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
            $payload['index'] = $this->name . '_v' . $indexVersion;
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

    public function indexMany(array $docs)
    {
        // @TODO: Optimize: Index many documents at once!
        foreach ($docs as $doc) {
            $this->index($doc);
        }
    }
}
