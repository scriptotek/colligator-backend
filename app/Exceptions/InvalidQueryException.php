<?php

namespace Colligator\Exceptions;

use RuntimeException;

class InvalidQueryException extends RuntimeException
{
    /**
     * Render into an HTTP response.
     *
     */
    public function render()
    {
        return \Response::make([
            'error' => 'invalid_query',
            'error_message' => $this->getMessage(),
        ]);
    }
}
