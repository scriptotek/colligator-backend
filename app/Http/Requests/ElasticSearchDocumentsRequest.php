<?php

namespace Colligator\Http\Requests;

use Colligator\Collection;

class ElasticSearchDocumentsRequest extends ElasticSearchRequest
{
    /**
     * Sanitize/standardize input before validation.
     */
    public function sanitize()
    {
        $input = parent::sanitize();

        $input['q'] = $this->queryStringFromRequest($input);

        $this->replace($input);
        return $input;
    }

    /**
     * Builds a query string query from a SearchDocumentsRequest.
     *
     * @param array $input
     * @return string
     */
    public function queryStringFromRequest(array $input)
    {
        $query = [];
        if (isset($input['q'])) {
            // Allow raw queries
            $query[] = $input['q'];
        }
        if (isset($input['collection'])) {
            $col = Collection::findOrFail($input['collection']);
            $query[] = 'collections:"' . $this->sanitizeForQuery($col->name) . '"';
        }
        if (isset($input['subject'])) {
            $query[] = '(subjects.noubomn.prefLabel:"' . $this->sanitizeForQuery($input['subject']) . '"' .
                ' OR subjects.bare.prefLabel:"' . $this->sanitizeForQuery($input['subject']) . '"' .
                ' OR genres.noubomn.prefLabel:"' . $this->sanitizeForQuery($input['subject']) . '")';
            // TODO: Vi bør vel antakelig skille mellom X som emne og X som form/sjanger ?
            //       Men da må frontend si fra hva den ønsker, noe den ikke gjør enda.
        }
        if (isset($input['language'])) {
            $query[] = 'language:"' . $this->sanitizeForQuery($input['language']) . '"' ;
        }
        if (isset($input['genre'])) {
            $query[] = 'genres.noubomn.prefLabel:"' . $this->sanitizeForQuery($input['genre']) . '"';
        }
        $query = count($query) ? implode(' AND ', $query) : '';

        return $query;
    }
}