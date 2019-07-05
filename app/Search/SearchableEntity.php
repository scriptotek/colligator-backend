<?php

namespace Colligator\Search;

use Colligator\Entity;

class SearchableEntity
{
    /**
     * @var Entity
     */
    protected $entity;

    /**
     * @param Entity $entity
     */
    public function __construct(Entity $entity)
    {
        $this->entity = $entity;
    }

    /**
     * Generate ElasticSearch index payload.
     *
     * @return array
     */
    public function toArray()
    {
        $body = $this->entity->toArray();

        return $body;
    }
}
