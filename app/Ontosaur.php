<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Ontosaur extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['url'];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'nodes' => 'array',
        'links' => 'array',
    ];
}
