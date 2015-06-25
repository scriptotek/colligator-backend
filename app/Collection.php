<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string name
 * @property string label
 */
class Collection extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'label'];

    /**
     * The documents belonging to the collection
     */
    public function documents()
    {
        return $this->belongsToMany('Colligator\Document');
    }

}
