<?php

namespace Colligator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class Request extends FormRequest
{
    /**
     * Validate the input.
     *
     * @param \Illuminate\Validation\Factory $factory
     *
     * @return \Illuminate\Validation\Validator
     */
    public function validator($factory)
    {
        return $factory->make(
            $this->sanitizeInput(), $this->container->call([$this, 'rules']), $this->messages()
        );
    }

    /**
     * Sanitize the input.
     * https://laracasts.com/discuss/channels/general-discussion/laravel-5-modify-input-before-validation/replies/34366.
     *
     * @return array
     */
    protected function sanitizeInput()
    {
        if (method_exists($this, 'sanitize')) {
            return $this->container->call([$this, 'sanitize']);
        }

        return $this->all();
    }
}
