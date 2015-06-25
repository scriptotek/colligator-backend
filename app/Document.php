<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'bibliographic' => 'array',
        'holdings' => 'array',
    ];

}
