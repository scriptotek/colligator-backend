<?php

namespace Colligator\Search;

use Colligator\Entity;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;

class EntitiesIndex extends ElasticSearchIndex
{
    public $model = Entity::class;
    public $modelRelationships = [];
    public $name = 'entities';
    public $documentType = 'entity';

    public $settings = [
        'analysis' => [
            'char_filter' => [
            ],
            'analyzer' => [
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
        ],
    ];

    /**
     * Add or update an entity in the ElasticSearch index, making it searchable.
     *
     * @param Entity $entity
     * @param int      $indexVersion
     *
     * @throws \ErrorException
     */
    public function index(Entity $entity, $indexVersion = null)
    {
        $payload = [
            'index' => $this->name,
            'type'  => $this->documentType,
        ];
        if (!is_null($indexVersion)) {
            $payload['index'] = $this->name . '_v' . $indexVersion;
        }
        $payload['id'] = $entity->id;

        $searchable = new SearchableEntity($entity);
        $payload['body'] = $searchable->toArray();

        try {
            $this->client->index($payload);
        } catch (BadRequest400Exception $e) {
            \Log::error('ElasticSearch returned error: ' . $e->getMessage() . '. Our request: ' . var_export($payload, true));
            throw new \ErrorException('ElasticSearch failed to index the entity ' . $entity->id . '. Please see the log for payload and full error response. Error message: ' . $e->getMessage());
        }
    }

    public function indexMany(array $entities)
    {
        // @TODO: Optimize: Index many documents at once!
        foreach ($entities as $entity) {
            $this->index($entity);
        }
    }

    public function indexByIds(array $updatedEntities)
    {
        foreach ($updatedEntities as $id) {
            $this->indexById($id);
        }
    }
}