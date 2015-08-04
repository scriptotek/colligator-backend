<?php

namespace Colligator\Exceptions;

use RuntimeException;

class CollectionNotFoundException extends RuntimeException
{
    /**
     * Render into an HTTP response.
     *
     */
    public function render()
    {
        return \Response::make(['error' => 'collection_not_found', 'error_message' => 'Collection not found']);
    }
}
