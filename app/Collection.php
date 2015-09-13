<?php

namespace Colligator;

use Colligator\Exceptions\CollectionNotFoundException;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'label'];

    /**
     * The documents belonging to the collection.
     */
    public function documents()
    {
        return $this->belongsToMany('Colligator\Document');
    }

    public static function findOrFail($id)
    {
        $collection = self::find($id);
        if (is_null($collection)) {
            throw new CollectionNotFoundException();
        }

        return $collection;
    }
}
