<?php

namespace Colligator\Http\Requests;

class ElasticSearchRequest extends Request
{
    // TODO: Move to some config file
    protected $maxResultsPerRequest = 1000;
    protected $maxPaginationDepth = 10000;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'offset' => "integer|gte:0|lte:{$this->maxPaginationDepth}",
            'limit' => "integer|gte:1|lte:{$this->maxResultsPerRequest}",
            'sort' => "string",
            'order' => "in:asc,desc",
        ];
    }

    /**
     * Sanitize/standardize input before validation.
     */
    public function sanitize()
    {
        $input = $this->all();

        if ($this->has('offset')) {
            $input['offset'] = intval($input['offset']);
        }

        if ($this->has('limit')) {
            $input['limit'] = intval($input['limit']);
        }

        $this->replace($input);

        return $input;
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
}