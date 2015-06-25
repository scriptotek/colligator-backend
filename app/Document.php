<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string bibsys_id
 * @property array bibliographic
 * @property array holdings
 */
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

    /**
     * The subjects belonging to the document
     */
    public function subjects()
    {
        return $this->belongsToMany('Colligator\Subject');
    }

}
