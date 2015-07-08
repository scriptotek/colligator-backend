<?php

namespace Colligator\Http\Requests;

use Colligator\Collection;

class SearchDocumentsRequest extends Request
{
    public $warnings = [];

    /**
     * Sanitize/standardize input.
     */
    public function sanitize()
    {

        // TODO: Move to some config file
        $maxResultsPerRequest = 1000;
        $maxPaginationDepth = 10000;

        $input = $this->all();

        if ($this->has('offset')) {
            $input['offset'] = intval($input['offset']);
            if ($input['offset'] < 0) {
                unset($input['offset']);
                $this->warnings[] = 'Offset cannot be negative.';
            } elseif ($input['offset'] > $maxPaginationDepth) {
                $input['offset'] = $maxPaginationDepth;
                $this->warnings[] = 'Pagination depth is limited to ' . $maxPaginationDepth . ' results.';
            }
        }

        if ($this->has('limit')) {
            $input['limit'] = intval($input['limit']);
            if ($input['limit'] < 1) {
                unset($input['limit']);
                $this->warnings[] = 'Limit cannot be negative.';
            } elseif ($input['limit'] > $maxResultsPerRequest) {
                $input['limit'] = $maxResultsPerRequest;
                $this->warnings[] = 'Limiting to max ' . $maxResultsPerRequest . ' results per request.';
            }
        }

        $this->replace($input);

        return $this->all();
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
}
